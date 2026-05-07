
if (!function_exists('jmm_smart_faq_allowed_post_types')) {
    function jmm_smart_faq_allowed_post_types() {
        return array('post', 'page', 'product');
    }
}

if (!function_exists('jmm_smart_faq_get_items')) {
    function jmm_smart_faq_get_items($content) {
        if (!is_string($content) || trim($content) === '') {
            return array();
        }

        if (
            stripos($content, 'faq') === false &&
            stripos($content, 'pertanyaan') === false
        ) {
            return array();
        }

        if (!class_exists('DOMDocument')) {
            return array();
        }

        libxml_use_internal_errors(true);

        $dom = new DOMDocument('1.0', 'UTF-8');
        $html = '<?xml encoding="utf-8" ?><div id="jmm-faq-wrapper">' . $content . '</div>';

        if (!$dom->loadHTML($html)) {
            libxml_clear_errors();
            return array();
        }

        libxml_clear_errors();

        $xpath = new DOMXPath($dom);
        $wrapper = $xpath->query('//*[@id="jmm-faq-wrapper"]')->item(0);

        if (!$wrapper) {
            return array();
        }

        $clean_text = function ($node_or_text) {
            if ($node_or_text instanceof DOMNode) {
                $html = '';

                foreach ($node_or_text->childNodes as $child) {
                    $html .= $node_or_text->ownerDocument->saveHTML($child);
                }

                $html = preg_replace('/<br\s*\/?>/iu', "\n", $html);
                $text = $html;
            } else {
                $text = (string) $node_or_text;
            }

            $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $text = wp_strip_all_tags($text);
            $text = str_replace("\xC2\xA0", ' ', $text);
            $text = preg_replace('/[ \t]+/u', ' ', $text);
            $text = preg_replace('/\s*\n\s*/u', "\n", $text);
            $text = preg_replace('/\n{2,}/u', "\n", $text);

            return trim($text);
        };

        $is_heading = function ($tag) {
            return in_array($tag, array('h1', 'h2', 'h3', 'h4', 'h5', 'h6'), true);
        };

        $is_faq_title = function ($text) {
            return preg_match(
                '/^(FAQ(?:\s+Singkat)?|FAQs?|Pertanyaan\s+Umum|Pertanyaan\s+yang\s+Sering\s+Diajukan)(?:\s*:)?$/iu',
                trim($text)
            );
        };

        $normalize_question = function ($text) {
            $text = trim($text);
            $text = preg_replace('/^\d+[\.\)]\s*/u', '', $text);
            $text = preg_replace('/^(Q|Question|Pertanyaan)\s*[:\-]\s*/iu', '', $text);
            return trim($text);
        };

        $is_question_like = function ($text) use ($normalize_question) {
            $text = $normalize_question($text);

            if ($text === '') {
                return false;
            }

            if (preg_match('/\?\s*$/u', $text)) {
                return true;
            }

            return preg_match(
                '/^(apa|apakah|bagaimana|kenapa|mengapa|kapan|di mana|dimana|siapa|berapa|dapatkah|bisakah|bolehkah|perlukah|haruskah|what|why|when|where|who|how|can|could|should|is|are|do|does)\b/iu',
                $text
            );
        };

        $split_inline_qa = function ($text) use ($normalize_question) {
            $text = trim($text);

            if (preg_match('/^(.+?\?)\s*(?:\n|\r\n|\r)\s*(.+)$/us', $text, $m)) {
                return array($normalize_question($m[1]), trim($m[2]));
            }

            if (preg_match('/^(.+?\?)\s+(.+)$/us', $text, $m)) {
                return array($normalize_question($m[1]), trim($m[2]));
            }

            if (preg_match('/^(.+?\?)(.+)$/us', $text, $m)) {
                return array($normalize_question($m[1]), trim($m[2]));
            }

            if (preg_match('/^(?:Q|Question|Pertanyaan)\s*[:\-]\s*(.+?)\s+(?:A|Answer|Jawaban)\s*[:\-]\s*(.+)$/iu', $text, $m)) {
                return array($normalize_question($m[1]), trim($m[2]));
            }

            return array('', '');
        };

        $query = '//*[@id="jmm-faq-wrapper"]//*[self::h1 or self::h2 or self::h3 or self::h4 or self::h5 or self::h6 or self::p or self::li]';
        $node_list = $xpath->query($query);

        $blocks = array();

        foreach ($node_list as $node) {
            $tag = strtolower($node->nodeName);

            if ($tag !== 'li') {
                $parent = $node->parentNode;
                $inside_li = false;

                while ($parent && $parent !== $wrapper) {
                    if (strtolower($parent->nodeName) === 'li') {
                        $inside_li = true;
                        break;
                    }

                    $parent = $parent->parentNode;
                }

                if ($inside_li) {
                    continue;
                }
            }

            $text = $clean_text($node);

            if ($text !== '') {
                $blocks[] = array(
                    'node' => $node,
                    'tag'  => $tag,
                    'text' => $text,
                );
            }
        }

        if (empty($blocks)) {
            return array();
        }

        $start_index = -1;

        foreach ($blocks as $index => $block) {
            if ($block['tag'] !== 'li' && $is_faq_title($block['text'])) {
                $start_index = $index + 1;
                break;
            }
        }

        if ($start_index < 0) {
            return array();
        }

        $items = array();
        $count = count($blocks);

        for ($i = $start_index; $i < $count; $i++) {
            $block = $blocks[$i];
            $node  = $block['node'];
            $tag   = $block['tag'];
            $text  = $block['text'];

            if ($text === '') {
                continue;
            }

            if ($is_heading($tag) && !$is_faq_title($text) && !$is_question_like($text)) {
                break;
            }

            if (preg_match('/^\[products\b/iu', $text)) {
                break;
            }

            $question = '';
            $answer   = '';

            if ($tag === 'li') {
                $strongs = $node->getElementsByTagName('strong');

                if ($strongs->length > 0) {
                    $question_raw = $clean_text($strongs->item(0));
                    $question = $normalize_question($question_raw);

                    $full_text = $clean_text($node);
                    $answer = trim(preg_replace('/^' . preg_quote($question_raw, '/') . '\s*/u', '', $full_text));
                }

                if ($question === '' || $answer === '') {
                    list($q, $a) = $split_inline_qa($text);

                    if ($q !== '' && $a !== '') {
                        $question = $q;
                        $answer = $a;
                    }
                }

                if ($question !== '' && $answer !== '') {
                    $items[] = array(
                        'question' => $question,
                        'answer'   => $answer,
                    );
                }

                continue;
            }

            $strongs = $node->getElementsByTagName('strong');

            if ($strongs->length > 0) {
                $question_raw = $clean_text($strongs->item(0));

                if ($is_question_like($question_raw)) {
                    $question = $normalize_question($question_raw);

                    $full_text = $clean_text($node);
                    $answer = trim(preg_replace('/^' . preg_quote($question_raw, '/') . '\s*/u', '', $full_text));

                    if ($answer === '' && isset($blocks[$i + 1])) {
                        $next = $blocks[$i + 1];

                        if (
                            !$is_question_like($next['text']) &&
                            !$is_faq_title($next['text']) &&
                            !($is_heading($next['tag']) && !$is_question_like($next['text'])) &&
                            !preg_match('/^\[products\b/iu', $next['text'])
                        ) {
                            $answer = $next['text'];
                            $i++;
                        }
                    }
                }
            }

            if ($question === '' || $answer === '') {
                list($q, $a) = $split_inline_qa($text);

                if ($q !== '' && $a !== '') {
                    $question = $q;
                    $answer = $a;
                }
            }

            if (($question === '' || $answer === '') && $is_question_like($text)) {
                $question = $normalize_question($text);

                if (isset($blocks[$i + 1])) {
                    $next = $blocks[$i + 1];

                    if (
                        !$is_question_like($next['text']) &&
                        !$is_faq_title($next['text']) &&
                        !($is_heading($next['tag']) && !$is_question_like($next['text'])) &&
                        !preg_match('/^\[products\b/iu', $next['text'])
                    ) {
                        $answer = $next['text'];
                        $i++;
                    }
                }
            }

            if ($question !== '' && $answer !== '') {
                $items[] = array(
                    'question' => wp_strip_all_tags($question),
                    'answer'   => wp_strip_all_tags($answer),
                );
            }
        }

        $unique = array();
        $seen = array();

        foreach ($items as $item) {
            $question = trim($item['question']);
            $answer   = trim($item['answer']);

            if ($question === '' || $answer === '') {
                continue;
            }

            $key_source = $question . '|' . $answer;
            $key = function_exists('mb_strtolower')
                ? md5(mb_strtolower($key_source, 'UTF-8'))
                : md5(strtolower($key_source));

            if (!isset($seen[$key])) {
                $seen[$key] = true;

                $unique[] = array(
                    'question' => $question,
                    'answer'   => $answer,
                );
            }
        }

        return $unique;
    }
}

/**
 * Generate FAQ Schema di frontend.
 */
if (!function_exists('jmm_auto_generate_smart_faq_schema')) {
    function jmm_auto_generate_smart_faq_schema($content) {
        if (is_admin() || is_feed() || is_preview()) {
            return $content;
        }

        if (!is_singular(jmm_smart_faq_allowed_post_types())) {
            return $content;
        }

        if (
            stripos($content, 'FAQPage') !== false &&
            stripos($content, 'application/ld+json') !== false
        ) {
            return $content;
        }

        static $done = array();

        $post_id = get_the_ID();

        if ($post_id && isset($done[$post_id])) {
            return $content;
        }

        $items = jmm_smart_faq_get_items($content);

        if (count($items) < 1) {
            return $content;
        }

        $mainEntity = array();

        foreach ($items as $item) {
            $mainEntity[] = array(
                '@type' => 'Question',
                'name'  => $item['question'],
                'acceptedAnswer' => array(
                    '@type' => 'Answer',
                    'text'  => $item['answer'],
                ),
            );
        }

        $schema = array(
            '@context'   => 'https://schema.org',
            '@type'      => 'FAQPage',
            'mainEntity' => $mainEntity,
        );

        $json_ld = '<script type="application/ld+json">' .
            wp_json_encode($schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) .
            '</script>';

        if ($post_id) {
            $done[$post_id] = true;
        }

        return $content . "\n" . $json_ld;
    }
}

add_filter('the_content', 'jmm_auto_generate_smart_faq_schema', 99);

/**
 * Admin column:
 * Hapus kolom FAQ Schema lama, lalu ganti dengan kolom baru dari smart detector.
 */
if (!function_exists('jmm_replace_faq_schema_admin_column')) {
    function jmm_replace_faq_schema_admin_column($columns) {
        $new_columns = array();
        $inserted = false;

        foreach ($columns as $key => $label) {
            $plain_label = trim(wp_strip_all_tags((string) $label));

            // Hapus semua kolom lama yang namanya FAQ Schema
            if (
                strtolower($plain_label) === 'faq schema' ||
                $key === 'jmm_faq_schema_status'
            ) {
                continue;
            }

            $new_columns[$key] = $label;

            // Masukkan kolom baru setelah Title
            if ($key === 'title') {
                $new_columns['jmm_faq_schema_status'] = 'FAQ Schema';
                $inserted = true;
            }
        }

        if (!$inserted) {
            $new_columns['jmm_faq_schema_status'] = 'FAQ Schema';
        }

        return $new_columns;
    }
}

if (!function_exists('jmm_render_faq_schema_admin_column')) {
    function jmm_render_faq_schema_admin_column($column, $post_id) {
        if ($column !== 'jmm_faq_schema_status') {
            return;
        }

        $content = get_post_field('post_content', $post_id);

        $already_has_schema = (
            stripos($content, 'FAQPage') !== false &&
            stripos($content, 'application/ld+json') !== false
        );

        $items = jmm_smart_faq_get_items($content);
        $count = count($items);

        if ($already_has_schema || $count > 0) {
            $title = $already_has_schema
                ? 'FAQPage schema sudah ada di konten.'
                : 'FAQ terdeteksi: ' . $count . ' item. Schema akan dibuat otomatis di frontend.';

            echo '<span title="' . esc_attr($title) . '" style="color:#16a34a;font-size:20px;font-weight:700;">✓</span>';
        } else {
            echo '<span title="FAQ tidak terdeteksi oleh smart parser." style="color:#dc2626;font-size:20px;font-weight:700;">×</span>';
        }
    }
}

if (!function_exists('jmm_register_faq_schema_admin_column')) {
    function jmm_register_faq_schema_admin_column() {
        foreach (jmm_smart_faq_allowed_post_types() as $post_type) {
            add_filter('manage_' . $post_type . '_posts_columns', 'jmm_replace_faq_schema_admin_column', 9999);
            add_action('manage_' . $post_type . '_posts_custom_column', 'jmm_render_faq_schema_admin_column', 9999, 2);
        }
    }
}

add_action('admin_init', 'jmm_register_faq_schema_admin_column');