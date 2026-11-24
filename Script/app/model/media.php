<?php
class MediaModel extends Model {
    public function ticket($track, $purpose) {
        $token = bin2hex(random_bytes(24));
        $this->db->query('INSERT INTO media_tickets (digest,userid,track,purpose,expires) VALUES(UNHEX(?),?,?,?,?)',
            hash('sha256',$token), (int)$this->C->model('user')->authId, $track['id'], $purpose, time()+300);
        return url('track/media/'.$token);
    }

    public function source($track, $purpose) {
        if (!$track) return false;
        if ($purpose === 'download') return $this->C->model('track')->canDownload($track) ? $track['track_file'] : false;
        if ($track['price'] > 0 && (!moduleExists('store') || !$this->C->model('store::store')->hasPurchased($track['id']))) {
            return $track['demo_file'] ?: false;
        }
        return $track['track_file'];
    }

    public function archive($playlistId) {
        $model = $this->C->model('track');
        $playlist = $model->findPlaylist($playlistId);
        if (!$playlist || ($playlist['public'] != 1 && $playlist['userid'] != $this->C->model('user')->authId)) return false;
        $albumPaid = $playlist['playlist_type'] == 0 && $playlist['price'] > 0;
        if ($albumPaid && (!moduleExists('store') || !$this->C->model('store::store')->hasPurchased($playlistId,'album',true))) return false;
        $entries = [];
        foreach ($model->getPlaylistTracks($playlistId,0) as $track) {
            if (!$track || (!$albumPaid && !$model->canDownload($track))) continue;
            $source = $track['track_file'];
            if (!$source) continue;
            $entries[] = ['id'=>(int)$track['id'],'source'=>$source,'title'=>toAscii($track['title'])];
        }
        if (!$entries) return false;
        $manifest = json_encode($entries, JSON_THROW_ON_ERROR);
        $id = hash('sha256',$playlistId.'|'.$manifest);
        $this->db->query('INSERT IGNORE INTO archive_jobs (id,manifest,updated_at) VALUES(?,?,?)',$id,$manifest,time());
        $job = $this->db->query('SELECT state FROM archive_jobs WHERE id=?',$id)->fetchColumn();
        return ['state'=>$job,'path'=>self::archiveDirectory().'/'.$id.'.zip'];
    }

    public static function archiveDirectory() {
        return getenv('MEDIA_ARCHIVE_DIR') ?: path('uploads/.archives');
    }

    public function deliver($source, $download, $name) {
        $file = $this->C->getFileUri($source,false);
        session_write_close();
        header('Cache-Control: private, no-store');
        if (preg_match('~^https?://~i',$file)) {
            if (str_starts_with($source,'s3-') || str_starts_with($source,'w3-')) {
                header('Location: '.$file); exit;
            }
            http_response_code(503); exit('Private object storage required for remote originals');
        }
        $absolute = realpath(str_starts_with($file,self::archiveDirectory().'/') ? $file : path($file));
        $uploads = realpath(path('uploads'));
        $private = realpath(dirname(rtrim(APP_BASE_PATH,'/')).'/musicengine-private');
        $archives = realpath(self::archiveDirectory());
        if (!$absolute || !is_file($absolute) || !(str_starts_with($absolute,$uploads.'/') || ($private && str_starts_with($absolute,$private.'/')) || ($archives && str_starts_with($absolute,$archives.'/')))) {
            http_response_code(404); exit;
        }
        $ext = pathinfo($absolute,PATHINFO_EXTENSION);
        header('Content-Disposition: '.($download ? 'attachment' : 'inline').'; filename="'.toAscii($name).'.'.$ext.'"');
        header('Content-Type: '.($download ? 'application/octet-stream' : getMimeType($absolute)));
        if (function_exists('apache_get_modules') && in_array('mod_xsendfile',apache_get_modules(),true)) {
            header('X-Sendfile: '.$absolute);
        } elseif ($download) {
            header('Content-Length: '.filesize($absolute)); readfile($absolute);
        } else {
            smartReadFile($absolute,basename($absolute),getMimeType($absolute));
        }
        exit;
    }
}
