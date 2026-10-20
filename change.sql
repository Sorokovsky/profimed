UPDATE wp_options SET option_value = replace(option_value, 'https://profimed.com.ua', 'http://localhost:8080');
UPDATE wp_posts SET post_content = replace(post_content, 'https://profimed.com.ua', 'http://localhost:8080');
UPDATE wp_postmeta SET meta_value = replace(meta_value, 'https://profimed.com.ua', 'http://localhost:8080');