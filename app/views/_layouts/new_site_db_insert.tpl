SET FOREIGN_KEY_CHECKS=0;

INSERT INTO `{site_prefix}option` VALUES(1, 'sitename', '{sitename}');
INSERT INTO `{site_prefix}option` VALUES(2, 'site_description', 'Just another Qubus CMS site');
INSERT INTO `{site_prefix}option` VALUES(3, 'admin_email', '{admin_email}');
INSERT INTO `{site_prefix}option` VALUES(4, 'ttcms_core_locale', 'en');
INSERT INTO `{site_prefix}option` VALUES(5, 'cookieexpire', '604800');
INSERT INTO `{site_prefix}option` VALUES(6, 'cookiepath', '/');
INSERT INTO `{site_prefix}option` VALUES(7, 'cron_jobs', '0');
INSERT INTO `{site_prefix}option` VALUES(8, 'system_timezone', 'America/New_York');
INSERT INTO `{site_prefix}option` VALUES(9, 'api_key', '{api_key}');
INSERT INTO `{site_prefix}option` VALUES(10, 'date_format', 'd F Y');
INSERT INTO `{site_prefix}option` VALUES(11, 'time_format', 'h:i A');
INSERT INTO `{site_prefix}option` VALUES(12, 'admin_skin', 'skin-purple-light');
INSERT INTO `{site_prefix}option` VALUES(13, 'site_cache', '0');
INSERT INTO `{site_prefix}option` VALUES(14, 'current_site_theme', '');
INSERT INTO `{site_prefix}option` VALUES(15, 'posts_per_page', '6');
INSERT INTO `{site_prefix}option` VALUES(16, 'maintenance_mode', '0');

INSERT INTO `{site_prefix}post` VALUES(1, 'Hello World', 'hello-world', '<p>Hello world! My name is Qubus CMS, and I was conceived on May 20, 2019. I am a content management framework with no default head. I can be used to store all your content while you use your skills as a developer to custom build a site around me or build RESTful applications. I am hoping that we can build a long lasting relationship.</p>', 1, 'post', NULL, 0, 0, 0, 'post/hello-world/', '', 'published', '2019-05-11 02:05:56 AM', '2019-05-11 02:05:56', '2019-05-11 02:05 AM', '2019-05-11 02:05:00', '2019-05-10 10:10:07 PM', '2019-05-10 22:10:07');

INSERT INTO `{site_prefix}posttype` VALUES(1, 'Post', 'post', '');
INSERT INTO `{site_prefix}posttype` VALUES(2, 'Page', 'page', '');

ALTER TABLE `{site_prefix}post`
  ADD CONSTRAINT `{site_prefix}post_post_author` FOREIGN KEY (`post_author`) REFERENCES `{base_prefix}user` (`user_id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `{site_prefix}post_post_parent` FOREIGN KEY (`post_parent`) REFERENCES `{site_prefix}post` (`post_id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `{site_prefix}post_post_posttype` FOREIGN KEY (`post_posttype`) REFERENCES `{site_prefix}posttype` (`posttype_slug`) ON DELETE CASCADE ON UPDATE CASCADE;

ALTER TABLE `{site_prefix}postmeta`
  ADD CONSTRAINT `{site_prefix}postmeta_post_id` FOREIGN KEY (`post_id`) REFERENCES `{site_prefix}post` (`post_id`) ON DELETE CASCADE ON UPDATE CASCADE;

SET FOREIGN_KEY_CHECKS=1;
