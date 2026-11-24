<?php
class ApiController extends Controller {

    private $userid;
    private $user;

    public function __construct($request = null) {
        parent::__construct($request);
        $apiKey = config('api-access-key', '');
        $providedKey = $this->request->segment(1);
        if (!$apiKey || !is_string($providedKey) || !hash_equals($apiKey, $providedKey)) {
            http_response_code(403);
            exit(json_encode(['status' => 0]));
        }
        header('Access-Control-Allow-Origin: http://localhost:9080');
    }

    private function userAuth($force = true) {
        $this->userid = $this->request->input('userid');
        $key = $this->request->input('key');
        $model = $this->model('user');
        $this->user = $model->credentialUser($key, 'mobile');
        if ($this->user && (string)$this->user['id'] === (string)$this->userid) {
            $model->authId = $this->user['id'];
            $model->authUser = $this->user;
        } else {
            $this->user = null;
            $model->authUser = null;
            $model->authId = null;
            if ($force) exit(json_encode(array('status' => 0)));
        }
    }

    public function setup() {
        $rgba = implode(',', hexToRgb(config('api-primary-color', '#FA0052')));
        $result = array(
            'default_language' => config('api-default-language', 'en'),
            'default_mode' => config('api-default-mode', 'dark'),
            'in_app_purchase' => config('api-enable-in-purchase', false),
            'premium_account' => (config('api-enable-premium', true)) ? true : false,
            'enable_store' => (moduleExists('store') and config('api-enable-store', true)) ? true : false,
            'enable_blogs' => (moduleExists('blog') and config('api-enable-blogs', true)) ? true : false,
            'enable_radios' => (moduleExists('radio') and config('api-enable-radios', true)) ? true : false,
            'enable_video' => (moduleExists('video') and config('api-enable-video', true)) ? true : false,
            'primary_color' => config('api-primary-color', '#FA0052'),
            'accent_color' => config('api-accent-color', '#141821'),
            'transparent_primary' => "rgba($rgba,0.4)",
            'slide_image_1' => (config('api_slide_image_1','')) ? assetUrl(config('api_slide_image_1','')) : '',
            'slide_image_2' => (config('api_slide_image_2','')) ? assetUrl(config('api_slide_image_2','')) : '',
            'slide_image_3' => (config('api_slide_image_3','')) ? assetUrl(config('api_slide_image_3','')) : '',
            'privacy_link' => config('api-privacy-link', ''),
            'terms_link' => config('api-terms-link', ''),
            'contact_link' => config('api-contact-link', ''),
            'progress' => (config('player-progress-type','bar') == 'bar') ? 'bar' : 'wave',
        );

        return json_encode($result);
    }

    public function login() {
        $username = $this->request->input('username');
        $password = $this->request->input('password');
        return $this->loginResponse($this->model('user')->loginUser($username, $password, true));
    }

    private function loginResponse($login) {
        if ($login === 'challenge') return json_encode(['status' => 2, 'challenge' => 'second_factor']);
        if ($login !== true) return json_encode(['status' => 0]);
        $user = $this->model('user')->authUser;
        return json_encode(array_merge($this->model('api')->formatUser($user, true), [
            'status' => 1, 'key' => $this->model('user')->issueCredential($user['id'], 'mobile'),
        ]));
    }

    public function completeFactor() {
        $user = $this->model('user')->challengeUser($_SESSION['pending_factor'] ?? null, 'factor', $this->request->input('code'));
        if (!$user || !$user['active']) return $this->loginResponse(false);
        $this->model('user')->loginWithObject($user);
        return $this->loginResponse(true);
    }

    public function socialSignup() {
        require_once path('app/vendor/classes/facebookidentity.php');
        $profile = FacebookIdentity::verify($_POST['access_token'] ?? '');
        $link = ($_POST['link_account'] ?? '') === '1';
        if ($link) $this->userAuth();
        return $this->loginResponse($profile ? $this->model('user')->facebookLogin($profile,$link) : false);
    }

    public function signup() {
        $val = array(
            'username' => $this->request->input('username'),
            'full_name' => $this->request->input('full_name'),
            'email' => $this->request->input('email'),
            'password' => $this->request->input('password')
        );
        $platform = $this->request->input('device');
        $validator = Validator::getInstance()->scan($val, array(
            'full_name' => 'required',
            'username' => 'required|username|predefined|alphanum|unique:users',
            'password' => 'required',
            'email' => 'required|email|unique:users'
        ));


        if ($validator->passes()) {
            $userid = $this->model('user')->addUser($val, false);
            if (config('email-activation', false)) {
                return json_encode(array(
                    'message' => l('signup-successful-activate'),
                    'status' => 2 //activate accoun my mail
                ));
            }


            $this->model('user')->loginUser($val['username'], $val['password'], true);
            if (config('auto-follow-accounts')) {
                $users = explode(',', config('auto-follow-accounts'));
                foreach($users as $followId) {
                    $followUser = $this->model('user')->getUser($followId);
                    if ($followUser) $this->model('user')->follow($followUser['id']);
                }
            }
            $this->userid = $this->model('user')->authId;
            $this->user = $this->model('user')->authUser;
            return json_encode(array_merge(array(
                'status' => 1,
                'key'  => $this->model('user')->issueCredential($this->userid, 'mobile')
            ), $this->model('api')->formatUser($this->user, true)));
        } else {
            return json_encode(array(
                'message' => $validator->first(),
                'status' => 0
            ));
        }
    }

    public function checkAuth() {
        $this->userAuth();
        $notifications = $this->model('user')->countUnreadNotifications();
        $messages = $this->model('message')->countUnread();
        return json_encode(array_merge(array(
            'notifications' => $notifications,
            'messages' => $messages,
            'total' => $notifications + $messages,
            'status' => 1
        ), $this->model('api')->formatUser($this->user, true)));

    }

    public function loadTracks() {
        $this->userAuth(false);
        $type = $this->request->input('type');
        $typeId = $this->request->input('type_id');
        $offset = $this->request->input('offset');
        $limit = ($this->request->input('limit')) ? $this->request->input('limit') : config('tracks-limit', 10);
        $tracks = $this->model('track')->getTracks($type, $typeId, $offset,$limit);
        return json_encode($this->model('api')->formatTrackPage($tracks, $type, $typeId));
    }

    public function activatePro() {
        http_response_code(403);
        return json_encode(['status' => 0, 'message' => 'Verified payment required']);
    }

    public function getGenres() {
        $this->userAuth(false);
        $term = $this->request->input('term', '');
        $result = array();
        foreach($this->model('admin')->getGenres($term) as $genre) {
            $result[] = array(
                'id' => $genre['id'],
                'name' => $genre['name'],
                'uses' => $genre['uses']
            );
        }

        return json_encode($result);
    }

    public function loadComments() {
        $offset = $this->request->input('offset');
        $limit = ($this->request->input('limit')) ? $this->request->input('limit') : config('comment-limit', 5);
        $this->userAuth(false);
        $type = $this->request->input('type');
        $typeId = $this->request->input('type_id');
        $comments = $this->model('track')->getComments($type, $typeId, $offset,$limit);
        //exit($offset .'-'.$limit.'-'.$type.'-'.$typeId);
        $result = array();
        foreach($comments as $comment) {
            $comment = $this->model('api')->formatComment($comment, $type, $typeId);
            if ($comment) $result[] = $comment;
        }

        return json_encode($result);
    }

    public function addComment() {
        $type = $this->request->input('type');
        $typeId = $this->request->input('type_id');
        $this->userAuth();
        $commentId = $this->model('track')->addComment(array(
            'type' => $type,
            'id' => $typeId,
            'comment_text' => $this->request->input('message'),
            'at' => $this->request->input('at')
        ));

        $comment = $this->model('track')->findComment($commentId);
        return json_encode($this->model('api')->formatComment($comment, $type, $typeId));
    }

    public function removeComment(){
        $id = $this->request->input('id');
        $this->userAuth();

        $comment = $this->model('track')->findComment($id);
        if ($comment['userid'] != $this->model('user')->authId) exit(json_encode(array('status' => 0)));
        $this->model('track')->deleteComment($id);
        exit(json_encode(array('status' => 1)));
    }

    public function trackDetails() {
        $id = $this->request->input('id');
        $this->userAuth(false);
        $track = $this->model('track')->findTrack($id);
        return json_encode($this->model('api')->formatTrack($track,null, null));
    }

    public function likeItem() {
        $type = $this->request->input('type');
        $typeId = $this->request->input('type_id');
        $this->userAuth(true);
        $like = $this->model('track')->likeItem($type, $typeId);
        return json_encode(array(
            'status' => 1
        ));
    }

    public function repostItem() {
        $action = $this->request->input('action');
        $track = $this->request->input('track');
        $playlistId = $this->request->input('playlist_id', '');
        $this->userAuth(true);
        $repost = $this->model('track')->addStream($track, $playlistId, $action);
        return json_encode(array(
            'status' => 1
        ));
    }

    public function navigatePlayer() {

        $offset = 0;
        $limit = 200;

        $trackId = $this->request->input('id');
        $track = $this->helperNavigate($offset, $limit, $trackId);
        return ($track) ? json_encode($track) : json_encode(array('id' => 0));
    }

    private function helperNavigate($offset, $limit,$trackId) {

        $type = $this->request->input('type');
        $typeId = $this->request->input('type_id');
        $this->userAuth(false);
        $navType = $this->request->input('nav_type');
        $tracks = $this->model('track')->getTracks($type, $typeId, $offset,$limit);
        $index = 'notfound';
        $i = 0;
        foreach($tracks as $track) {
            $trackid = (isset($track['trackid'])) ? $track['trackid'] : $track['id'];
            if ($trackid == $trackId) {
                $index = $i;
                break;
            }
            $i++;
        }
        $newIndex = ($navType == 'next') ? $index + 1 : $index - 1;
        if ($newIndex < 0) $newIndex = count($tracks) - 1;
        return (isset($tracks[$newIndex])) ? $this->model('api')->formatTrack($tracks[$newIndex],$type,$typeId) : null;

    }

    public function getMyPlaylists() {
        $playlists = $this->model('track')->getPlaylists();
        $trackId = $this->request->input('track');
        $result = array();
        $this->userAuth(false);
        foreach($playlists as $list) {
            $track = model('track')->getPlaylistFirstTrack($list['id']);
            $result[] =  array(
                'name' => $list['name'],
                'art' => $this->model('track')->getArt($track),
                'count' => model('track')->countPlaylistTracks($list['id']),
                'id' => $list['id'],
                'contain' => (model('track')->existInPlaylist($list['id'], $trackId)) ? 1 : 0
            );
        }
        return json_encode($result);
    }

    public function addToPlaylist() {
        $this->userAuth();
        $trackId = $this->request->input('track');
        $playlistId = $this->request->input('playlist');

        $this->model('track')->addToPlaylist($playlistId, $trackId);
        return json_encode(array('status' => 1));
    }

    public function addPlaylist() {
        $name = $this->request->input('name');
        $desc = $this->request->input('desc');
        $public = $this->request->input('public');
        $track = $this->request->input('track');
        $this->userAuth();

        $this->model('track')->addPlaylist(array(
            'title' => $name,
            'desc' => $desc,
            'public' => $public
        ), array($track));

        return json_encode(array('status' => 1));
    }

    public function getTrackDownloadDetail() {
        $this->userAuth(false);
        $track = $this->model('track')->findTrack($this->request->input('id'));
        if (!$track || !$this->model('track')->canDownload($track)) { http_response_code(403); return json_encode(['status' => 0]); }
        $extension = get_file_extension($track['track_file']) ?: 'mp3';
        $result = [
            'url' => $this->model('media')->ticket($track,'download'),
            'name' => md5($track['title']).'.'.$extension,
        ];
        return json_encode($result);

    }

    public function listPeople() {
        $type = $this->request->input('type');
        $offset = $this->request->input('offset');
        $limit = $this->request->input('limit');
        $term = $this->request->input('term');
        $userid = $this->request->input('theuserid', null);
        $this->userAuth(false);
        switch($type) {
            case 'artists':
                $users = $this->model('user')->getPeople('top',$term,  $limit, $offset);
                break;
            case 'people':
                $users = $this->model('user')->getPeople('people',$term,  $limit, $offset);
                break;
            case 'followers':
                $users = $this->model('user')->getFollows($userid, 2, $limit, $offset);
                break;
            case 'following':
                $users = $this->model('user')->getFollows($userid, 1, $limit, $offset);
                break;
        }
        $result = array();
        foreach($users as $user) {
            $result[] = $this->model('api')->formatUser($user);
        }

        return json_encode($result);
    }

    public function listPlaylist() {
        $type = $this->request->input('type');
        $typeId = $this->request->input('type_id');

        $offset = $this->request->input('offset');
        $limit = $this->request->input('limit');
        $this->userAuth(false);
        $playlists = $this->model('track')->listPlaylists($type, $typeId,  $limit, $offset);
        $result = array();
        foreach($playlists as $playlist) {
            $result[] = $this->model('api')->formatPlaylist($playlist);
        }

        return json_encode($result);
    }

    public function hasSpotlight() {
        $this->userAuth(false);
        $userid = $this->request->input('theuserid');

        $tracks = model('track')->getTracks('my-spotlight', $userid,0, 5);
        if (count($tracks) > 0) {
            return json_encode(array('status' => 1));
        } else {
            return json_encode(array('status' => 0));
        }
    }

    public function getPurchased() {
        $this->userAuth();
        $type = $this->request->input('type');
        $transactions = model('store::store')->getPurchased($type);
        $result = array();
        foreach($transactions as $transaction) {
            $details = array(
                'time' => date('c', $transaction['time'])
            );
            switch($type) {
                case 'track':
                    $track = model('track')->findTrack($transaction['type_id']);
                    $details['track'] = $this->model('api')->formatTrack($track,null,null);
                    break;
                case 'album':
                    $playlist = model('track')->findPlaylist($transaction['type_id']);
                    $details['playlist'] = $this->model('api')->formatPlaylist($playlist);
                    break;
                case 'video':
                    $video = model('video::video')->find($transaction['type_id']);
                    $details['video'] = $this->model('api')->formatVideo($video);
                    break;
            }
        }
        return json_encode($result);
    }

    public function listVideo() {
        $type = $this->request->input('type');
        $typeId = $this->request->input('type_id');

        $offset = $this->request->input('offset');
        $limit = $this->request->input('limit');
        $this->userAuth(false);
        $videos = $this->model('video::video')->getVideos($type,$typeId,$offset, $limit);
        $result = array();
        foreach($videos as $video) {
            $result[] = $this->model('api')->formatVideo($video);
        }

        return json_encode($result);
    }

    public function changeAvatar() {
        $this->userAuth();
        $avatar = $this->request->inputFile('avatar');
        $uploader = new Uploader($avatar);
        $uploader->setPath('avatar/'.$this->model('user')->authId.'/');
        if ($uploader->passed()) {
            $avatar = $uploader->resize()->result();
            $user = $this->model('user')->getUser($this->model('user')->authId);
            $this->model('user')->saveUser(array('avatar' => $avatar), $user);
            return json_encode(array('status' => 1, 'avatar' => $this->model('user')->getAvatar(model('user')->getUser(model('user')->authId), 200)));
        } else {
            return json_encode(array('status' => 0, 'message' => $uploader->getError()));
        }
    }

    public function saveAccount() {
        $this->userAuth();
        $val = array(
            'full_name' => $this->request->input('name'),
            'website' => $this->request->input('website'),
            'bio' => $this->request->input('bio'),
            'facebook' => $this->request->input('facebook'),
            'instagram' => $this->request->input('instagram'),
            'twitter' => $this->request->input('twitter'),
            'soundcloud' => $this->request->input('soundcloud'),
            'youtube' => $this->request->input('youtube'),
            'vimeo' => $this->request->input('vimeo'),
            'city' => $this->request->input('city')
        );

        $user = $this->model('user')->getUser($this->model('user')->authId);
        $this->model('user')->saveUser($val, $user);

        $user = $this->model('user')->getUser($this->model('user')->authId);
        return json_encode($this->model('api')->formatUser($user));
    }

    public function userDetail() {
        $this->userAuth(false);
        $userid = $this->request->input('id');
        $user = $this->model('user')->getUser($userid);
        return json_encode($this->model('api')->formatUser($user));
    }

    public function notificationList() {
        $this->userAuth();
        $offset = $this->request->input('offset');
        $limit = $this->request->input('limit');
        $notifications = $this->model('user')->getNotifications($offset, $limit);
        $result = array();
        foreach($notifications as $notification) {
            $result[] = $this->model('api')->formatNotification($notification);
        }
        return json_encode($result);
    }

    public function deleteNotification() {
        $this->userAuth();
        $notificationId = $this->request->input('id');
        $this->model('user')->deleteNotification($notificationId);
        return json_encode(array('status' => 1));
    }

    public function listMessages() {
        $this->userAuth();
        $conversations = $this->model('message')->getConversations($this->request->input('offset', 0), $this->request->input('limit', 25));
        $result = array();
        foreach($conversations as $conversation) {
            $conversation['user'] = $this->model('api')->formatUser($conversation['user']);
            $message = $conversation['lastMessage'];
            $message['chattime'] = date('c', $message['time']);
            $conversation['message'] = $message;
            $result[] = $conversation;
        }
        return json_encode($result);
    }

    public function chats() {
        $this->userAuth();
        $offset = $this->request->input('offset');
        $limit = $this->request->input('limit');
        $cid = $this->request->input('cid');
        $to = $this->request->input('to');
        if (!$cid) $cid = $this->model('message')->getConversationId($this->model('user')->authId, $to);
        $messages = $this->model('message')->getMessages($cid, $offset, $limit);
        $result = array();
        foreach($messages as $message) {
            $result[] = $this->model('api')->formatMessage($message);
        }

        return json_encode($result);
    }

    public function sendChat() {
        $this->userAuth();
        $text = $this->request->input('text');
        $to = $this->request->input('to');
        $messageId = $this->model('message')->send($to, $text, '', '');
        $message = $this->model('message')->getMessage($messageId);
        $message = $this->model('api')->formatMessage($message);
        return json_encode($message);
    }

    public function priceDetail() {
        $price = array(
            'yearly' => config('pro-user-yearly-price', '55'),
            'monthly' => config('pro-user-month-price', '5')
        );

        if (!config('enable-premium-listeners', false)) {
            $price = array(
                'yearly' => config('pro-artist-yearly-price', '55'),
                'monthly' => config('pro-artist-month-price', '5')
            );
        }
        return json_encode($price);
    }

    public function trackPlay() {
        $id = $this->request->input('id');
        $this->userAuth();
        if (!$this->model('track')->hasView($this->model('user')->authId, $id)) $this->model('track')->setViews($id);
        return json_encode(array('status' => 1));
    }

    public function videoPlay() {
        $id = $this->request->input('id');
        $this->userAuth();
        //$video = $this->model('video::video')->find($id);
        $this->model('video::video')->addPlays($id);
        return json_encode(array('status' => 1));
    }

    public function videoView() {
        $id = $this->request->input('id');
        $this->userAuth(false);
        $video = $this->model('video::video')->find($id);
        $this->model('video::video')->updateViews($video);
        return json_encode(array('status' => 1));
    }

    public function playVideo() {
        $id = $this->request->input('id');
        $this->userAuth(false);
        $video = $this->model('video::video')->find($id);

        return $this->view('api/video-player', array('video' => $video));
    }

    public function suggestVideos() {
        $id = $this->request->input('id');
        $this->userAuth(false);
        $videos = model('video::video')->getSuggestedVideos($id, 10);
        $result = array();
        $result = array();
        foreach($videos as $video) {
            $result[] = $this->model('api')->formatVideo($video);
        }

        return json_encode($result);
    }

    public function userFollow() {
        $this->userAuth();
        $userid = $this->request->input('id');
        $action = $this->model('user')->follow($userid);
        return json_encode(array('status' => 1));
    }

    public function pay() {
        $type = $this->request->input('type');
        $typeId = $this->request->input('type_id');
        $price = $this->request->input('price');
        if ($type == 'pro-users' and !config('enable-premium-listeners', false)) $type = 'pro';
        $this->userAuth();

        $this->model('user')->loginWithObject($this->user);
        $price = $this->model('order')->create($type, $typeId);
        session_put("about-pay-type", $type);
        session_put('about-pay-typeid', $typeId);
        session_put("about-pay-price", $price);
        session_put("mobile-pay", 1);

        $view = $this->view('payment/methods', array(
            'type' => $type,
            'typeId' => $typeId,
            'price' => $price,
            'detail' => $this->model('admin')->getPaymentDetails($type, $typeId)
        ));
        return $this->view('api/pay', array('view' => $view)) ;
    }

    public function radio() {
        $type = $this->request->input('type');
        $typeId = $this->request->input('type_id');
        $platform = $this->request->input('platform', 'android');
        $offset = $this->request->input('offset');
        $limit = $this->request->input('limit');
        $this->userAuth(false);
        $radios = $this->model('radio::radio')->getRadios($type,$typeId,$offset, $limit);
        $result = array();
        foreach($radios as $radio) {
            $radio = $this->model('api')->formatRadio($radio, $platform);
            if ($radio) $result[] = $radio;
        }

        return json_encode($result);
    }

    public function blogs() {
        $type = $this->request->input('type');
        $typeId = $this->request->input('type_id');

        $offset = $this->request->input('offset');

        $this->userAuth(false);
        $blogs = $this->model('blog::blog')->getBlogs($type,$typeId,$offset);
        $result = array();
        foreach($blogs as $blog) {
            $result[] = $this->model('api')->formatBlog($blog);
        }

        return json_encode($result);
    }

    public function uploadPicture() {
        $this->userAuth();
        $type = $this->request->input('type');
        if ($type == 'avatar') {
            $avatar = $this->request->inputFile('avatar');
            $uploader = new Uploader($avatar);
            $uploader->setPath('avatar/'.$this->model('user')->authId.'/');
            if ($uploader->passed()) {
                $avatar = $uploader->resize()->result();
                $user = $this->model('user')->getUser($this->model('user')->authId);
                $this->model('user')->saveUser(array('avatar' => $avatar), $user);
                return json_encode(array('status' => 1, 'avatar' => $this->model('user')->getAvatar(model('user')->getUser(model('user')->authId), 200)));
            } else {
                return json_encode(array('status' => 0, 'message' => $uploader->getError()));
            }
        } else {
            $avatar = $this->request->inputFile('avatar');
            $uploader = new Uploader($avatar);
            $uploader->setPath('cover/'.$this->model('user')->authId.'/');
            if ($uploader->passed()) {
                $avatar = $uploader->uploadFile()->result();
                $user = $this->model('user')->getUser($this->model('user')->authId);
                $this->model('user')->saveUser(array('cover' => $avatar), $user);
                return json_encode(array('status' => 1, 'cover' => assetUrl($avatar)));
            } else {
                return json_encode(array('status' => 0, 'message' => $uploader->getError()));
            }
        }
    }


}