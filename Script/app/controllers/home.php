<?php
class HomeController extends Controller {

    public function before()
    {

        //if ($this->model('user')->)
    }

    public function index() {
        if ($this->model('user')->isLoggedIn()) {

            return $this->home();
        }
        $default = config('default-homepage', 'splash');
        if ($default != 'splash') {
            switch ($default) {
                case 'charts':
                    return $this->charts();
                    break;
                case 'discover':
                    return $this->discover();
                    break;
            }
        }
        if(is_ajax()) exit('login');
        $this->setTitle("");
        $this->setLayout("includes/plain-layout");
        return $this->render($this->view('home/index'));
    }

    public function home() {
        $type = $this->request->segment(0, 'feed');
        $this->setTitle(l($type));
        $this->addBreadCrumb(l($type));
        $this->activeMenu = 'feed';

        $content = $this->view('home/track-list', array('type' => 'feed'));
        return $this->render($this->view('home/feed', array('type' => 'feed', 'content' => $content)), true);
    }

    public function upload() {
        $this->setTitle(l('upload-a-song'));
        $this->addBreadCrumb(l('upload'));
        $this->collapsed = true;
        if (!$this->model('user')->isAuthor() and  !$this->model('user')->isAdmin()) return $this->request->redirect(url(''));
        if ($val = $this->request->input('val')) {
            //validate each of custom url in case there is any

            $ownerId = ($this->model('user')->isAdmin() and $this->request->input('id')) ? $this->request->input('id') : null;

            foreach($val['customurl'] as $url) {
                if ($url) {
                    $valid = preg_match('/^[\pL\pN_-]+$/u', $url);
                    if (!$valid) {
                        return json_encode(array(
                            'type' => 'error',
                            'message' => l('custom-url-alphanum', array('url' => $url))
                        ));
                    }
                }
            }

            $validator = Validator::getInstance()->scan($val, array(
               'titles' => 'required',
                //'tags' => 'required'
            ));

            if ($validator->passes()) {

                $artFile = $this->request->inputFile('img');
                $audioFiles = $this->request->input('val.trackfiles');
                $images = array();
                $count = 0;
                while($count < count($audioFiles) ) {
                    $artFile = $this->request->inputFile('img_'.$count);
                    if ($artFile) {
                        $artUpload = new Uploader($artFile);
                        $artUpload->setPath("tracks/".$this->model('user')->authId.'/art/'.date('Y').'/');
                        if ($artUpload->passed()) {
                            $val['art'] = $artUpload->resize()->result();
                            $val['art_holder'] = $val['art'];
                            $val['image_'.$count] = $val['art'];
                        } else {
                            return json_encode(array(
                                'message' => $artUpload->getError(),
                                'type' => 'error'
                            ));
                        }
                    } else {
                        $images[] = '';
                    }
                    $count++;
                }

                $lyricsUploaded = array();


                $i = 0;
                foreach($audioFiles as $audioFile) {
                    if ($lyricsFile = $this->request->inputFile('lyrics_'.$i)) {
                        $upload = new Uploader($lyricsFile, 'file');
                        $upload->setPath("tracks/".$this->model('user')->authId.'/lyric/'.date('Y').'/');
                        if ($upload->passed()) {
                            $val['lyrics_'.$i] = $upload->uploadFile()->result();
                        } else {
                            return json_encode(array(
                                'message' => l('lyric-file-error'),
                                'type' => 'error'
                            ));
                        }
                    }
                    $i++;
                }

                $fileSizes = 0;
                foreach($val['sizes'] as $size) {
                    $fileSizes += $size;
                }

                if(config('enable-premium', false)) {
                    $usedSpace = $this->model('user')->getTracksSpace() + $fileSizes;
                    $allowSize = $this->model('user')->getTotalTrackSize() * 1024 * 1000;
                    if ($usedSpace > $allowSize) {
                        //use have used up your allowed space
                        return json_encode(array(
                            'message' => l('not-enough-space'),
                            'type' => 'error'
                        ));
                    }
                }


                $tracks = array();
                //$audioFiles = array_reverse($audioFiles);
                $i = 0;

                foreach ($audioFiles as $audio) {
                    $newVal = array();
                    $newVal['size'] = $val['sizes'][$i];
                    $newVal['title'] = $val['titles'][$i];
                    if (!$newVal['title']) break;
                    $newVal['slug'] = $val['customurl'][$i];
                    $newVal['wave'] = $val['titlewaves'][$i];
                    $newVal['wave_colored'] = $val['titlewaves2'][$i];
                    $newVal['lyrics'] = (isset($val['lyrics_'.$i])) ? $val['lyrics_'.$i] : '';
                    $newVal['featuring'] = (isset($val['featuring'][$i])) ? $val['featuring'][$i] : '';
                    $tmpMove = $audio;
                    $newVal['duration'] = $val['durations'][$i];
                    $newVal['audio'] = $audio;
                    if ($val['day'][$i] and $val['month'][$i] and $val['year'][$i]) {
                        $newVal['release'] = date("Y-m-d", mktime(0, 0, 0, $val['month'][$i], $val['day'][$i], $val['year'][$i]));
                    } else {
                        $newVal['release'] = '';
                    }
                    $newVal['release_date'] = (isset($val['release_date'][$i])) ? $val['release_date'][$i] : '';


                    $imageContent = (isset($val['avatardata'][$i]) && is_string($val['avatardata'][$i]))
                        ? trim($val['avatardata'][$i])
                        : '';

                    if (!$imageContent) {
                        $newVal['art'] = (isset($val['art'])) ? $val['art'] : '';
                        $albumArt = $newVal['art'];
                    } else {
                        $newVal['art'] = (isset($val['art'])) ? $val['art'] : '';
                        $artPath = 'uploads/tracks/'.$this->model('user')->authId.'/tracks/art/'.date('Y').'/';
                        $artDirectory = path($artPath);
                        $userArtDirectory = 'uploads/tracks/'.$this->model('user')->authId.'/tracks/art/';
                        $trustedArtDirectory = realpath(path($userArtDirectory));
                        $artVariants = (strpos($imageContent, '%w') !== false)
                            ? array_map(function($size) use ($imageContent) { return str_replace('%w', $size, $imageContent); }, array(75, 200, 600, 920))
                            : array($imageContent);
                        $isUploadedArt = false;
                        if ($trustedArtDirectory && strncmp($imageContent, $userArtDirectory, strlen($userArtDirectory)) === 0) {
                            foreach ($artVariants as $artVariant) {
                                $submittedArtFile = realpath(path($artVariant));
                                if ($submittedArtFile
                                    && strpos($submittedArtFile, $trustedArtDirectory.DIRECTORY_SEPARATOR) === 0
                                    && is_file($submittedArtFile)
                                    && @getimagesize($submittedArtFile) !== false) {
                                    $isUploadedArt = true;
                                    break;
                                }
                            }
                        }

                        if ($isUploadedArt) {
                            $newVal['art'] = $imageContent;
                        } else {
                            $imageContent = preg_replace('#^data:image/[^;]+;base64,#i', '', $imageContent);
                            $imageContent = str_replace(' ', '+', $imageContent);
                            $imageBytes = base64_decode($imageContent, true);
                            if ($imageBytes === false) {
                                return json_encode(array(
                                    'message' => l('upload-file-not-valid-image'),
                                    'type' => 'error'
                                ));
                            }

                            if (!is_dir($artDirectory)) {
                                @mkdir($artDirectory, 0777, true);
                            }
                            if (!is_dir($artDirectory)) {
                                return json_encode(array(
                                    'message' => l('upload-file-not-valid-image'),
                                    'type' => 'error'
                                ));
                            }

                            $indexFile = $artDirectory.'index.html';
                            if (!file_exists($indexFile)) {
                                @file_put_contents($indexFile, '');
                            }

                            $image = @imagecreatefromstring($imageBytes);
                            if ($image === false) {
                                return json_encode(array(
                                    'message' => l('upload-file-not-valid-image'),
                                    'type' => 'error'
                                ));
                            }

                            $width = imagesx($image);
                            $height = imagesy($image);
                            $scale = min(1, 920 / max($width, $height));
                            if ($scale < 1) {
                                $scaledWidth = max(1, (int) round($width * $scale));
                                $scaledHeight = max(1, (int) round($height * $scale));
                                $scaledImage = imagecreatetruecolor($scaledWidth, $scaledHeight);
                                imagealphablending($scaledImage, false);
                                imagesavealpha($scaledImage, true);
                                imagecopyresampled($scaledImage, $image, 0, 0, 0, 0, $scaledWidth, $scaledHeight, $width, $height);
                                imagedestroy($image);
                                $image = $scaledImage;
                            }

                            $imageFile = $artDirectory.md5($imageBytes.time().$i).'.png';
                            if (@imagepng($image, $imageFile, 7)) {
                                $newVal['art'] = $artPath.basename($imageFile);
                            }
                            imagedestroy($image);
                        }
                        $albumArt = $newVal['art'];
                    }
                    if (isset($val['image_'.$count])) $val['art'] = $val['image_'.$i];
                    $newVal['embed'] = $val['embed_'.$i];
                    $newVal['download'] = $val['download_'.$i];
                    $newVal['privacy'] = $val['privacy_'.$i];
                    $newVal['label'] = $val['label'][$i];
                    $newVal['link'] = $val['link'][$i];
                    $newVal['genre'] = $val['genre'][$i];
                    $newVal['tags'] = $val['tags'][$i];
                    $newVal['description'] = $val['description'][$i];


                    if ($val['license_'.$i] == 2) {
                        $newVal['license'] = '2-'.$val['noncommercial'][$i].'-'.$val['creative_second_'.$i];
                    }



                    $newVal = Hook::getInstance()->fire('track.upload.data', $newVal, array($i));
                    $tracks[] = $this->model('track')->add($newVal, null, false, $ownerId);

                    $i++;
                }

                if (count($tracks) == 1 and !$val['playlist']) {
                    $this->model('track')->addStream($tracks[0], 0,'posted-track', $ownerId);
                    Hook::getInstance()->fire("tracks.added.controller", null, array($tracks));
                    return json_encode(array(
                        'message' => l('track-successful'),
                        'type' => 'url',
                        'value' => $this->model('track')->trackUrl(null, $tracks[0])
                    ));

                } else {
                    if ($val['playlist']) {
                        $playList = $this->model('track')->addPlaylist(array(
                            'title' => $val['playlist'],
                            'type' => $val['playlist_type'],
                            'art' => (!isset($val['art_holder'])) ? $albumArt : $val['art_holder'],
                            'desc' => $val['playlist_desc'],
                            'public' => (isset($val['release_date'][0]) and $val['release_date'][0]) ? 3 : 1,
                            'release_date' => (isset($val['release_date'][0])) ? $val['release_date'][0] : $val['privacy'][0]
                        ),  $tracks, $ownerId);
                        if ($val['playlist_type'] == 0) Hook::getInstance()->fire('upload.album', null, array($playList));
                        $this->model('track')->addStream($tracks[0], $playList,'posted-album', $ownerId);
                        $playList = $this->model('track')->findPlaylist($playList);
                        Hook::getInstance()->fire("tracks.added.controller", null, array($tracks));
                        return json_encode(array(
                            'message' => l('track-successful'),
                            'type' => 'url',
                            'value' => $this->model('track')->playlistUrl($playList)
                        ));
                    } else {
                        //probably load to the my tracks page
                        //we need to post this to this user stream for their followers
                        foreach($tracks as $track) {
                            $this->model('track')->addStream($track, 0, 'posted-track', $ownerId);
                        }
                        Hook::getInstance()->fire("tracks.added.controller", null, array($tracks));
                        return json_encode(array(
                            'message' => l('track-successful'),
                            'type' => 'url',
                            'value' => $this->model('track')->trackUrl(null, $tracks[0])
                        ));
                    }
                }
            } else {
                return json_encode(array(
                    'message' => $validator->first(),
                    'type' => 'error'
                ));
            }
        }
        return $this->render($this->view('upload/song'), true);
    }

    public function login() {

        if ($val = $this->request->input('val')) {
            $username = $val['username'];
            $password = $val['password'];


            if ($login = $this->model('user')->loginUser($username, $password, true)) {
                Hook::getInstance()->fire('user.login.start');
                if (preg_match('#not-active#', $login)) {
                    list($text, $id) = explode('|', $login);
                    return json_encode(array(
                        'message' => l('login-successful'),
                        'type' => 'normal-url',
                        'content' => '#authModal',
                        'value' => url('activate/account', array('id' => $id))
                    ));
                }
                if ($login === 'challenge') {
                    return json_encode(array(
                        'message' => l('login-successful'),
                        'type' => 'normal-url',
                        'content' => '#authModal',
                        'value' => url('two/factor/auth', array('id' => $_SESSION['pending_factor']))
                    ));
                }
                return json_encode(array(
                    'message' => l('login-successful'),
                    'type' => 'normal-url',
                    'content' => '#authModal',
                    'value' => url()
                ));
            } else {
                return json_encode(array(
                    'message' => l('failed-login'),
                    'type' => 'error'
                ));
            }
        }
        if (!config('separate-login', 0)) $this->request->redirect(url());
        $this->setTitle(l('sign-in'));
        $this->setLayout("includes/plain-layout");
        return $this->render($this->view('auth/screen', array('heading'=>'welcome-back', 'mode'=>'login')));
    }

    public function signup() {
        if ($val = $this->request->input('val')) {
            if (config('enable-captcha', true)) {
                $captcha = $this->request->input('captcha');
                include_once path('captcha/securimage.php');
                $securimage = new Securimage();
                if (!$securimage->check($captcha)) {
                    return json_encode(array(
                        'message' => l('invalid-captcha-code'),
                        'type' => 'error-function',
                        'value' => 'reloadCaptcha'
                    ));
                }
            }
            $validator = Validator::getInstance()->scan($val, array(
                'full_name' => 'required',
                'username' => 'required|predefined|alphanum|username|unique:users',
                'password' => 'required',
                'email' => 'required|email|unique:users'
            ));


            if ($validator->passes()) {
                $userid = $this->model('user')->addUser($val, false);
                if (config('email-activation', false)) {
                    return json_encode(array(
                        'message' => l('signup-successful-activate'),
                        'type' => 'modal-function',
                        'modal' => '#authModal'
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
                $url = url();
                if (config('enable-welcome', true)) {
                    $url = url('welcome');
                }
                return json_encode(array(
                    'message' => l('signup-successful'),
                    'type' => 'normal-url',
                    'content' => '#authModal',
                    'value' => $url
                ));
            } else {
                return json_encode(array(
                    'message' => $validator->first(),
                    'type' => 'error'
                ));
            }
        }
        if (!config('separate-login', 0) or !config('user-signup', true)) $this->request->redirect(url());
        $this->setTitle(l('create-account'));
        $this->setLayout("includes/plain-layout");
        return $this->render($this->view('auth/screen', array('heading'=>'create-account', 'mode'=>'signup')));
    }

    public function welcome() {
        $this->setLayout("includes/plain-layout");
        return $this->render($this->view('welcome/index'));
    }

    public function welcomeLoad() {
        $users = $this->model('user')->getPeople('top', 'random', 20, 0, array(
            'genres' => $this->request->input('genres'),
            'users' => array($this->model('user')->authId)
        ));
        return $this->view('welcome/users', array('users' => $users));
    }

    public function welcomeFinish() {
        $users = explode(',', $this->request->input('users'));
        foreach($users as $user) {
            $this->model('user')->follow($user);
        }

    }

    public function logout() {
        $this->model('user')->logoutUser();
        $this->request->redirect(url());
    }

    public function searchTags() {
        return $this->model('track')->searchTags($this->request->input('term'));
        return json_encode(array(
            array('value' => 'twalo', 'text' => 'twalo'),
            array('value' => 'kola', 'text' => 'kola'),
            array('value' => 'waliu', 'text' => 'waliu'),
            array('value' => 'was', 'text' => 'was')
            ));
    }

    public function charts() {
        $this->setTitle(l('charts'));
        $this->activeMenu = 'charts';
        $page = $this->request->segment(1, 'new-hot');
        $genre = $this->request->input('genre', 'all');
        $time = $this->request->input('time', config(($page == 'new-hot') ? 'chart-new-hot-time' : 'chart-top-time', 'this-week'));

        if ($page == 'top') $this->activeMenu = "charts-top";
        $this->addBreadCrumb(($page == 'top') ? l('top-50') : l($page));

        return $this->render($this->view($page === 'top' ? 'charts/top' : 'charts/index', array('page' => $page, 'genre' => $genre, 'time' => $time)), true);
    }

    public function discover() {
        $this->setTitle(l('discover'));
        $this->addBreadCrumb(l('discover'), url('discover'));

        $page = $this->request->segment(1, 'overview');
        $this->addBreadCrumb(l($page));
        switch($page) {
            case 'genre':
                $id = explode('-', $this->request->segment(2));
                $id = $id[0];
                $genre = $this->model('admin')->findGenre($id);
                $this->addBreadCrumb($genre['name']);
                $content = $this->view('discover/genre', array('id' => $id));
                break;
            case 'latest':
                $content = $this->view('discover/latest');
                break;
            case 'artists':
                $this->activeMenu = 'artists';
                $content = $this->view('discover/artists');
                break;
            case 'albums':
                $this->activeMenu = 'albums';
                $content = $this->view('playlist/lists', array('type' => 'album', 'typeId' => 'discover'));
                break;
            case 'playlists':
                $content = $this->view('playlist/lists', array('type' => 'playlist', 'typeId' => 'discover'));
                break;
            default:
                $content = $this->view('discover/overview');
                break;
        }
        return $this->render($this->view('discover/layout', array('content' => $content, 'page' => $page)), true);
    }

    public function collection() {
        $page = $this->request->segment(1, 'lister-later');
        $this->setTitle(l($page));
        $this->addBreadCrumb(l('collection'));
        $this->addBreadCrumb(l($page));
        $this->activeMenu = $page;
        switch($page) {
            case 'listen-later':
                $content = $this->view('collection/lists', array('type' => 'listen-later', 'typeId' => '', 'viewType' => 'inline'));
                break;
            case 'likes':
                $content = $this->view('collection/lists', array('type' => 'likes', 'typeId' => ''));
                break;
            case 'history':
                $content = $this->view('collection/lists', array('type' => 'history', 'typeId' => ''));
                break;
            case 'playlists':
                $content = $this->view('playlist/lists', array('type' => 'playlist', 'typeId' => 'collection'));
                break;
            case 'albums':
                $this->activeMenu = 'collection-albums';
                $content = $this->view('playlist/lists', array('type' => 'album', 'typeId' => 'collection'));
                break;
        }
        return $this->render($this->view('collection/layout', array('content' => $content, 'page' => $page)), true);

    }

    public function clearHistory() {
        $this->model('track')->clearHistory();
        return json_encode(array(
            'type' => 'url',
            'message' => l('track-history-cleared'),
            'value' => url('collection/history')
        ));
    }

    public function search() {
        $term = $this->request->input('term');
        $this->addBreadCrumb(l('search'));
        $page = $this->request->input('page', 'tracks');
        $this->addBreadCrumb(l($page));
        switch ($page) {
            case 'artists':
                $content = $this->view('search/people', array('term' => $term, 'type' => 'top'));
                break;
            case 'people':
                $content = $this->view('search/people', array('term' => $term, 'type' => 'people'));
                break;
            case 'playlists':
                $content = $this->view('playlist/lists', array('type' => 'playlist', 'typeId' => 'search-'.$term));
                break;
            case 'albums':
                $content = $this->view('playlist/lists', array('type' => 'album', 'typeId' => 'search-'.$term));
                break;
            case 'videos':
                $content = $this->view('search/video', array('type' => 'search', 'typeid' => $term));
                break;
            case 'radio':
                $content = $this->view('search/radio', array('type' => 'search', 'typeid' => $term));
                break;
            default:
                $content = $this->view('search/tracks', array('term' => $term));
                break;
        }
        return $this->render($this->view('search/layout', array('term' => $term, 'page' => $page, 'content' => $content)), true);
    }

    public function searchDropdown() {
        $term = $this->request->input('term');

        return $this->view('search/dropdown', array('term' => $term));
    }

    public function pricing(){
        $this->setTitle(l('pro-plans'));
        if (config('enable-premium', false)) {
            if (!$this->model('user')->subscriptionActive()) {
                $this->useBreadcrumbs = false;
                $this->collapsed = true;
            } else {
                return $this->request->redirect(url('settings/pro'));
            }
        }


        return $this->render($this->view('pro/index'),true);
    }

    public function trypro() {
        $user = $this->model('user')->authUser;
        $hasTried = $user['has_tried'];
        if(!config('enable-trial') and $hasTried) return $this->request->redirect(url());
        $this->model('admin')->addTransaction(array(
            'name' => $user['full_name'],
            'email' => 'Trial',
            'country' =>'Trial',
            'amount' => 'Trial',
            'sale_id' => 'Trial',
            'type' => 'pro',
            'type_id' => 'monthly',
            'userid' => $user['id']
        ));
        Database::getInstance()->query("UPDATE users SET has_tried=? WHERE id=?", 1, $user['id']);
        return $this->request->redirect(url('settings/pro'));
    }

    public function share() {
        return $this->view('share/index', array('id' => $this->request->input('id'), 'type' => $this->request->input('type')));
    }

    public function embedCode() {
        $color = '#'.$this->request->input('color', 'FF6138');
        $autoPlay = $this->request->input('autoplay', false);
        $renderType = $this->request->input('type', 'picture');
        $type = $this->request->segment(1, 'track');
        list($id, $slug) = explode('-', $this->request->segment(2));

        return $this->view('share/embed', array('color' => $color,
            'autoplay' => $autoPlay, 'renderType' => $renderType, 'type' => $type, 'id' => $id));
    }

    public function changeLanguage() {
        $id = $this->request->input('id');
        setcookie("language", $id, time() + 90 * 24 * 60 * 60, config('cookie_path'));
        return $this->request->redirectBack();
    }

    public function stats() {
        $this->setTitle(l('statistics'));
        $time = $this->request->input('time', 'today');
        $id = $this->request->input('id', 'all');
        $chart = $this->request->input('chart', config('default-chart-type', 'bar'));
        if (!in_array($time, array('today', '7days', '30days', '12month', 'total'), true)) $time = 'today';
        if (!in_array($chart, array('bar', 'line', 'area'), true)) $chart = 'bar';
        if ($id !== 'all') {
            if (!ctype_digit((string) $id) || (int) $id < 1) return $this->request->redirect(url('statistics'));
            $track = $this->model('track')->findTrack((int) $id, false);
            if (!$track || ((int) $track['userid'] !== (int) $this->model('user')->authId && !$this->model('user')->isAdmin())) {
                return $this->request->redirect(url('statistics'));
            }
        }
        $strtime = $this->model('track')->getStrTime($time);
        if ($id != 'all') {
            $this->addBreadCrumb(l('track'), $this->model('track')->trackUrl($track));
            $this->addBreadCrumb($track['title']);
        }
        return $this->render($this->view('statistics/index',array(
            'time' => $time,
            'id' => $id,
            'chart' => $chart,
            'strtime' => $strtime
        )), true);
    }

    public function loadCharts() {
        $time = $this->request->input('time');
        $id = $this->request->input('id');
        if (!in_array($time, array('today', '7days', '30days', '12month', 'total'), true)) $time = 'today';
        if ($id !== 'all') {
            if (!ctype_digit((string) $id) || (int) $id < 1) return json_encode(array('error' => 'Invalid track'));
            $track = $this->model('track')->findTrack((int) $id, false);
            if (!$track || ((int) $track['userid'] !== (int) $this->model('user')->authId && !$this->model('user')->isAdmin())) {
                return json_encode(array('error' => 'Track not found'));
            }
        }

        $result = array(
            'charts' => array()
        );



        $times = array();
        switch($time) {
            case 'today':
                $todayStart = strtotime("today");
                $i = 0;
                while($i < 24) {
                    $start = $todayStart + (60 * 60 * $i);
                    $end = $start + 3600 - 1;
                    $times[date('h a', $start)] = array($start, $end);
                    $i++;
                }
                break;
            case '7days':
                $i = 6;
                while($i >= 0) {
                    $start = strtotime("-$i day");
                    $start = strtotime('today', $start);
                    $end= $start + (60*60*24) - 1;
                    $times[date(' D', $start)] = array($start, $end);
                    $i--;
                }
                break;
            case '30days':
                $i = 29;
                while($i >= 0) {
                    $start = strtotime("-$i day");
                    $start = strtotime('today', $start);
                    $end= $start + (60*60*24) - 1;
                    $times[date(' M d', $start)] = array($start, $end);
                    $i--;
                }
                break;
            case '12month':
                $i = 11;
                while($i >= 0) {
                    $start = strtotime('first day of this month', strtotime("-$i month"));
                    $end = strtotime('first day of next month', $start) - 1;
                    $times[date(' M y', $start)] = array($start, $end);
                    $i--;
                }
                break;
            case 'total':
                $i = 2017;
                $currentYear = date('Y');
                while($i<=$currentYear) {
                    $start = strtotime("first day of January $i");
                    $end = ($i == $currentYear) ? time() : strtotime('first day of January '.($i + 1)) - 1;
                    $times[date(' Y', $start)] = array($start, $end);
                    $i++;
                }
                break;
        }
        $plays = array(
            'name' => l('plays'),
            'points' => array()
        );
        $likes = array(
            'name' => l('likes'),
            'points' => array()
        );
        $comments = array(
            'name' => l('comments'),
            'points' => array()
        );
        $downloads = array(
            'name' => l('downloads'),
            'points' => array()
        );
        $listeners = array(
            'name' => l('listeners'),
            'points' => array()
        );
        foreach($times as $name => $time) {
            $stats = $this->model('track')->countTrackStatistics($id,$time[0], $time[1]);
            $plays['points'][$name] = $stats['plays'];
            $likes['points'][$name] = $stats['likes'];
            $comments['points'][$name] = $stats['comments'];
            $downloads['points'][$name] = $stats['downloads'];
            $listeners['points'][$name] = $stats['listeners'];
        }
        $result['charts']['stats'] = array($plays, $likes,$comments,$downloads,$listeners);


        $result = Hook::getInstance()->fire('user.charts', $result, array($times));

        return json_encode($result);
    }

    public function page() {
        $id = $this->request->segment(1);
        $page = $this->model('admin')->findPage($id);
        if (!$page) $this->request->redirect(url());
        $this->collapsed = true;
        if ($page['location'] == 'side') $this->activeMenu = 'page-'.$page['id'];
        $this->setTitle(l($page['title']));
        $this->addBreadCrumb(l($page['title']));

        return $this->render($this->view('pages/index', array('page' => $page)), true);
    }

    public function authFacebook() {
        require_once path('app/vendor/classes/facebookidentity.php');
        $redirect = url('auth/facebook');
        $code = $this->request->input('code');
        if (!$code) {
            $link = ($_POST['link_account'] ?? '') === '1' && $this->model('user')->isLoggedIn();
            if ($link) requireMutationToken();
            $_SESSION['facebook_link_owner'] = $link ? $this->model('user')->authId : null;
            $_SESSION['facebook_state'] = bin2hex(random_bytes(32));
            return $this->request->redirect('https://www.facebook.com/dialog/oauth?'.http_build_query([
                'client_id' => config('facebook-key'), 'redirect_uri' => $redirect,
                'scope' => 'email', 'state' => $_SESSION['facebook_state'],
            ]));
        }
        $state = $_SESSION['facebook_state'] ?? '';
        $link = !empty($_SESSION['facebook_link_owner']) && $_SESSION['facebook_link_owner'] == $this->model('user')->authId;
        unset($_SESSION['facebook_state'], $_SESSION['facebook_link_owner']);
        $provided = $this->request->input('state');
        if (!$state || !is_string($provided) || !hash_equals($state, $provided)) { http_response_code(403); return 'Invalid sign-in state'; }
        $profile = FacebookIdentity::verify(FacebookIdentity::exchange($code, $redirect));
        $login = false;
        if ($profile) $login = $this->model('user')->facebookLogin($profile, $link);
        if ($login === false) { http_response_code(403); return 'Unable to verify or link this identity'; }
        return $this->request->redirect($login === 'challenge' ? url('two/factor/auth', ['id' => $_SESSION['pending_factor']]) : url());
    }

    public function saveThemeMode() {
        $mode = $this->request->input('mode');
        session_put('theme-mode', $mode);
    }

    function urlElement($url, $time) {
        echo '<url>'.PHP_EOL;
        echo '<loc>'.$url.'</loc>'. PHP_EOL;
        echo '<changefreq>monthly</changefreq>'.PHP_EOL;
        echo '<lastmod>'.date('c', $time).'</lastmod>'.PHP_EOL;
        echo '<priority>0.8</priority>'.PHP_EOL;
        echo '</url>'.PHP_EOL;
    }

    public function sitemap() {
        header("Content-Type: application/xml; charset=utf-8");
        echo '<?xml version="1.0" encoding="UTF-8"?>'.PHP_EOL;
        echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xsi:schemaLocation="http://www.sitemaps.org/schemas/sitemap/0.9 http://www.sitemaps.org/schemas/sitemap/0.9/sitemap.xsd">' .PHP_EOL;

        $this->urlElement(url('/'), time());
        $this->urlElement(url('pro'), time());
        $this->urlElement(url('charts'), time());
        $this->urlElement(url('charts/trending'), time());
        $this->urlElement(url('charts/top'), time());
        $this->urlElement(url('discover'), time());
        $this->urlElement(url('discover/latest'), time());
        $this->urlElement(url('discover/artists'), time());
        $this->urlElement(url('discover/albums'), time());
        $this->urlElement(url('discover/playlists'), time());
        $this->urlElement(url('videos'), time());
        $this->urlElement(url('videos/latest'), time());
        $this->urlElement(url('videos/top'), time());
        foreach($this->model('admin')->getGenres() as $genre) {
            $this->urlElement(url('videos/category/'.$genre['id']), time());
        }

        $query = Database::getInstance()->query("SELECT * FROM tracks");
        while($track = $query->fetch(PDO::FETCH_ASSOC)) {
            $this->urlElement($this->model('track')->trackUrl($track), $track['time']);
        }

        $query = Database::getInstance()->query("SELECT * FROM users");
        while($user = $query->fetch(PDO::FETCH_ASSOC)) {
            $this->urlElement($this->model('user')->profileUrl($user), time());
        }

        $query = Database::getInstance()->query("SELECT * FROM playlist");
        while($playlist = $query->fetch(PDO::FETCH_ASSOC)) {
            $this->urlElement($this->model('track')->playlistUrl($playlist), $playlist['time']);
        }

        $query = Database::getInstance()->query("SELECT * FROM videos");
        while($video = $query->fetch(PDO::FETCH_ASSOC)) {
            $this->urlElement($this->model('video::video')->videoUrl($video), $video['time']);
        }
        echo '</urlset>';
    }
}
