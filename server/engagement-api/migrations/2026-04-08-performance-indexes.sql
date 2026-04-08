ALTER TABLE post_comments
  ADD KEY post_comments_public_lookup_idx (post_path, status, created_at);