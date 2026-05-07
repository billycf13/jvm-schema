function faq_schema_shortcode() {
global $post;

if (!$post) return '';

$content = $post->post_content;

// Cari section FAQ
preg_match('/<h2[^>]*id="pertanyaan-yang-sering-diajukan"[^>]*>.*?<\ /h2>(.*)/is',
        $content, $sectionMatch);

        if (!isset($sectionMatch[1])) {
        return '<!-- FAQ Schema: Section tidak ditemukan -->';
        }

        $faqSection = $sectionMatch[1];

        // Pattern khusus untuk struktur kamu:
        // <h3>Pertanyaan</h3> diikuti text langsung (bukan <p>)
            // Capture sampai ketemu
        <h3> berikutnya atau <h2> berikutnya atau akhir content
                preg_match_all('/<h3[^>]*>(.*?)<\ /h3>\s*(.*?)(?=<h[23]|$) /is', $faqSection, $matches, PREG_SET_ORDER);
                            $faqs=[]; foreach ($matches as $match) { $question=wp_strip_all_tags($match[1]);
                            $answer=wp_strip_all_tags($match[2]); // Clean up whitespace
                            $question=trim(preg_replace('/\s+/', ' ' , $question));
                            $answer=trim(preg_replace('/\s+/', ' ' , $answer)); // Validasi minimal length if
                            (strlen($question)> 5 && strlen($answer) > 20) {
                            $faqs[] = [
                            "@type" => "Question",
                            "name" => $question,
                            "acceptedAnswer" => [
                            "@type" => "Answer",
                            "text" => $answer
                            ]
                            ];
                            }
                            }

                            if (empty($faqs)) {
                            return '<!-- FAQ Schema: Tidak ada FAQ valid ditemukan -->';
                            }

                            // Set tracking meta
                            update_post_meta($post->ID, '_has_faq_schema', '1');
                            update_post_meta($post->ID, '_faq_count', count($faqs));
                            update_post_meta($post->ID, '_faq_last_updated', current_time('mysql'));

                            // Generate JSON-LD Schema
                            $schema = [
                            "@context" => "https://schema.org",
                            "@type" => "FAQPage",
                            "mainEntity" => $faqs
                            ];

                            return '
                            <script type="application/ld+json">' . 
           json_encode($schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . 
           '</script>';
                            }
                            add_shortcode('faq_schema', 'faq_schema_shortcode');

                            // Register meta fields
                            function register_faq_schema_meta() {
                            register_post_meta('post', '_has_faq_schema', [
                            'show_in_rest' => true,
                            'single' => true,
                            'type' => 'string',
                            ]);
                            register_post_meta('post', '_faq_count', [
                            'show_in_rest' => true,
                            'single' => true,
                            'type' => 'integer',
                            ]);
                            register_post_meta('post', '_faq_last_updated', [
                            'show_in_rest' => true,
                            'single' => true,
                            'type' => 'string',
                            ]);
                            }
                            add_action('init', 'register_faq_schema_meta');

                            // Kolom monitoring di Posts list
                            // function faq_schema_column($columns) {
                            // $columns['faq_schema'] = 'FAQ Schema';
                            // return $columns;
                            // }
                            // add_filter('manage_posts_columns', 'faq_schema_column');

                            // function faq_schema_column_content($column, $post_id) {
                            // if ($column == 'faq_schema') {
                            // $has_schema = get_post_meta($post_id, '_has_faq_schema', true);
                            // $faq_count = get_post_meta($post_id, '_faq_count', true);

                            // if ($has_schema) {
                            // echo '✅ ' . $faq_count . ' FAQ';
                            // } else {
                            // $content = get_post_field('post_content', $post_id);
                            // if (strpos($content, '[faq_schema]') !== false) {
                            // echo '⚠️ Shortcode ada';
                            // } else {
                            // echo '❌';
                            // }
                            // }
                            // }
                            // }
                            // add_action('manage_posts_custom_column', 'faq_schema_column_content', 10, 2);