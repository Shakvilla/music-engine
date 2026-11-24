CREATE INDEX views_track_user ON views (track,userid);
CREATE INDEX likes_item_user ON likes (type,typeid,userid);
CREATE INDEX comments_item ON comments (type,typeid);
CREATE INDEX downloads_track ON downloads (track);
CREATE INDEX stream_track_action ON stream (trackid,action);
CREATE INDEX chat_conversation_time ON chat (cid,time,id);
CREATE INDEX chat_unread ON chat (cid,to_uid,is_read);
CREATE INDEX views_time_track ON views (time,track);
