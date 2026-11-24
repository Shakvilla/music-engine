<?php
class MessageModel extends Model {
    private const MESSAGE_FIELDS = "chat.cid,chat.message,chat.time,chat.from_uid,chat.to_uid,chat.trackid,chat.playlistid,chat.is_read,chat.id AS chatid,chat.time AS chattime,users.id,users.username,users.full_name,users.avatar,users.cover,users.bio,users.country,users.city,users.website,users.facebook,users.twitter,users.youtube,users.vimeo,users.soundcloud,users.gender";

    public function send($to, $message='', $track = '', $playlist='') {
        $userid = $this->C->model('user')->authId;
        $cid = $this->getConversationId($userid, $to);
        $query = $this->db->query("INSERT INTO chat (from_uid,to_uid,cid,message,trackid,playlistid,time)VALUES(?,?,?,?,?,?,?)",
            $userid, $to, $cid, $message, $track, $playlist, time());

        $messageId = $this->db->lastInsertId();
        $this->db->query("UPDATE conversations SET time=? WHERE id=?", time(), $cid);
        return $messageId;
    }

    public function getConversationId($userid, $to) {
        $query = $this->db->query("SELECT id FROM conversations WHERE (user1=? AND user2=?) OR (user1=? AND user2=?) LIMIT 1 ", $userid,$to, $to, $userid);
        if ($query->rowCount() > 0) {
            $result = $query->fetch(PDO::FETCH_ASSOC);
            return $result['id'];
        } else {
            $query = $this->db->query("INSERT INTO conversations (user1,user2,time)VALUES(?,?,?)", $userid, $to, time());
            return $this->db->lastInsertId();
        }
    }

    public function countUnread() {
        $query = $this->db->query("SELECT id FROM chat WHERE to_uid=? AND is_read=? ", $this->C->model('user')->authId, 0);
        return $query->rowCount();
    }

    public function getNewUnreadMessages($time) {
        $query = $this->db->query("SELECT ".self::MESSAGE_FIELDS." FROM chat LEFT JOIN users ON users.id=chat.from_uid WHERE to_uid=? AND chat.time > ?  ORDER BY chattime DESC LIMIT 1",
            $this->C->model('user')->authId, $time);
        $results = $query->fetchAll(PDO::FETCH_ASSOC);
        $formatResults = array();
        foreach($results as $result){
            $formatResults[] = $this->formatMessage($result);
        }

        return $formatResults;
    }

    public function getMessages($cid, $offset= 0, $limit = 10) {
        $query = $this->db->query("SELECT ".self::MESSAGE_FIELDS." FROM chat LEFT JOIN users ON users.id=chat.from_uid WHERE chat.cid=? AND (chat.from_uid=? OR chat.to_uid=?) ORDER BY chattime  DESC LIMIT ? OFFSET ?",
            $cid, $this->C->model('user')->authId, $this->C->model('user')->authId, paginationNumber($limit === '' ? 10 : $limit, true), paginationNumber($offset, false));
        $result = array();
        while($fetch= $query->fetch(PDO::FETCH_ASSOC)) {
            $result[] = $this->formatMessage($fetch);
        }
        return array_reverse($result);
    }

    public function getMessage($messageId) {
        $query = $this->db->query("SELECT ".self::MESSAGE_FIELDS." FROM chat LEFT JOIN users ON users.id=chat.from_uid WHERE chat.id=? AND (chat.from_uid=? OR chat.to_uid=?) LIMIT 1",
            $messageId, $this->C->model('user')->authId, $this->C->model('user')->authId);
        $row = $query->fetch(PDO::FETCH_ASSOC);
        return $row ? $this->formatMessage($row) : null;
    }

    public function formatMessage($message) {
        if ($message['trackid']) {
            $message['track'] = $this->C->model('track')->findTrack($message['trackid']);
        }

        if ($message['playlistid']) {
            $message['playlist'] = $this->C->model('track')->findPlaylist($message['playlistid']);
        }
        $message['avatar'] = $this->C->model('user')->getAvatar($message, 200);
        return $message;
    }

    public function getLastCid() {
        $userid = $this->C->model('user')->authId;
        $query = $this->db->query("SELECT id FROM conversations WHERE user1=? OR user2=? ORDER BY time DESC LIMIT 1", $userid,$userid);
        if ($query->rowCount() > 0) {
            $fetch = $query->fetch(PDO::FETCH_ASSOC);
            return $fetch['id'];
        }
        return false;
    }

    public function getConversations($offset = 0, $limit = 25) {
        $userid = $this->C->model('user')->authId;
        $query = $this->db->query("SELECT c.*, u.id AS peer_id, u.username, u.full_name, u.avatar, u.cover,
            u.bio,u.country,u.city,u.website,u.facebook,u.twitter,u.youtube,u.vimeo,u.soundcloud,u.gender,
            (SELECT COUNT(*) FROM chat WHERE cid=c.id AND to_uid=? AND is_read=0) AS unread,
            (SELECT id FROM chat WHERE cid=c.id ORDER BY time DESC,id DESC LIMIT 1) AS latest_id
            FROM conversations c JOIN users u ON u.id=IF(c.user1=?,c.user2,c.user1)
            WHERE c.user1=? OR c.user2=? ORDER BY c.time DESC,c.id DESC LIMIT ? OFFSET ?",
            $userid,$userid,$userid,$userid,paginationNumber($limit,true),paginationNumber($offset,false));
        $rows = $query->fetchAll(PDO::FETCH_ASSOC);
        $ids = array_filter(array_column($rows, 'latest_id'));
        $messages = [];
        if ($ids) {
            $parameters = implode(',', array_fill(0,count($ids),'?'));
            $query = $this->db->query("SELECT * FROM chat WHERE id IN ($parameters)", array_values($ids));
            while ($row = $query->fetch(PDO::FETCH_ASSOC)) $messages[$row['id']] = $row;
        }
        $result = [];
        foreach ($rows as $row) {
            $conversation = array_intersect_key($row, array_flip(['id','user1','user2','time','unread']));
            $conversation['lastMessage'] = $messages[$row['latest_id']] ?? null;
            $conversation['user'] = array_diff_key($row, $conversation, ['latest_id'=>1,'peer_id'=>1]);
            $conversation['user']['id'] = $row['peer_id'];
            $result[] = $conversation;
        }
        return $result;
    }

    public function formatConversation($fetch) {
        $userid = $this->C->model('user')->authId;
        $fetch['user'] = ($fetch['user1'] == $userid) ? $this->C->model('user')->getUser($fetch['user2'], false) : $this->C->model('user')->getUser($fetch['user1'], false);
        $fetch['lastMessage'] = $this->getLastMessage($fetch['id']);
        $fetch['unread'] = $this->countConversationUnread($fetch['id']);
        return $fetch;
    }

    public function getLastMessage($cid) {
        $query = $this->db->query("SELECT * FROM chat WHERE cid=? ORDER BY time DESC LIMIT 1", $cid);
        return $query->fetch(PDO::FETCH_ASSOC);
    }

    public function findConversation($cid) {
        $query = $this->db->query("SELECT * FROM conversations WHERE id=? AND (user1=? OR user2=?) ", $cid, $this->C->model('user')->authId, $this->C->model('user')->authId);
        $row = $query->fetch(PDO::FETCH_ASSOC);
        return $row ? $this->formatConversation($row) : null;
    }

    public function countConversationUnread($cid) {
        $query = $this->db->query("SELECT id FROM chat WHERE cid=? AND from_uid !=? AND is_read=? ORDER BY time DESC", $cid,
            $this->C->model('user')->authId, 0);
        return $query->rowCount();
    }

    public function markSeen($id) {
        return $this->db->query("UPDATE chat SET is_read=? WHERE id=? AND to_uid=?", 1, $id, $this->C->model('user')->authId);
    }
}