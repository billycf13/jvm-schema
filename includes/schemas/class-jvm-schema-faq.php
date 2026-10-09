<?php
/**
 * FAQPage schema generator — explicit wrapper + smart auto-detection.
 *
 * Detection strategies (in order):
 *   1. Explicit  <div class="jvm-faq"> wrappers
 *      (handles: headings h1-h6, <li> inline Q&A, <p><strong>Q</strong><br>A</p>)
 *   2. Headings (h1-h6) with question-like text, followed by content
 *   3. <details>/<summary> accordion pattern
 *   4. Inline Q&A in list items (<li>) — via DOMDocument
 *   5. Inline Q&A in paragraph (<p><strong>Q</strong><br>A</p>) — via DOMDocument
 *
 * Content sources:
 *   - Article / blog post: post_content
 *   - WooCommerce product: post_content + custom tab contents
 *     (_custom_product_tabs, yikes_woo_products_tabs)
 *
 * @package JVM_Schema
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class JVM_Schema_FAQ {

    /**
     * Build and return the FAQPage schema array.
     *
     * @return array|null
     */
    public function get_schema() {
        if ( '1' !== get_option( 'jvm_schema_enable_faq', '1' ) ) {
            return null;
        }

        if ( ! is_singular() ) {
            return null;
        }

        $post = get_post();
        if ( ! $post ) {
            return null;
        }

        $content = $this->gather_content( $post );
        if ( empty( trim( $content ) ) ) {
            return null;
        }

        $faqs = $this->extract_faqs( $content );

        if ( empty( $faqs ) ) {
            return null;
        }

        $main_entity = array();
        foreach ( $faqs as $faq ) {
            $main_entity[] = array(
                '@type'          => 'Question',
                'name'           => $faq['question'],
                'acceptedAnswer' => array(
                    '@type' => 'Answer',
                    'text'  => $faq['answer'],
                ),
            );
        }

        return array(
            '@context'   => 'https://schema.org',
            '@type'      => 'FAQPage',
            'mainEntity' => $main_entity,
        );
    }

    /* ──────────────────────────────────────────────
     *  Content gathering
     * ────────────────────────────────────────────── */

    /**
     * Gather all relevant content from the current page.
     *
     * @param WP_Post $post Current post object.
     * @return string Combined HTML content.
     */
    private function gather_content( $post ) {
        $parts = array( $post->post_content );

        // WooCommerce product: include custom tab contents.
        $is_wc_product = ( function_exists( 'is_product' ) && is_product() ) || ( isset( $post->post_type ) && 'product' === $post->post_type );

        if ( $is_wc_product ) {
            // Source 1: Generic custom tabs plugin (_custom_product_tabs).
            $custom_tabs = get_post_meta( $post->ID, '_custom_product_tabs', true );
            if ( is_array( $custom_tabs ) ) {
                $parts = array_merge( $parts, $this->process_tab_list( $custom_tabs ) );
            }

            // Source 2: Yikes "Custom Product Tabs for WooCommerce" plugin (yikes_woo_products_tabs).
            $yikes_tabs = get_post_meta( $post->ID, 'yikes_woo_products_tabs', true );
            if ( is_array( $yikes_tabs ) ) {
                $parts = array_merge( $parts, $this->process_tab_list( $yikes_tabs ) );
            }
        }

        return implode( "\n\n", array_filter( $parts ) );
    }

    /**
     * Process a list of custom tabs and return content parts.
     *
     * Each tab array must have:
     *   - 'title'   (string) Tab label — used to detect if it's an FAQ tab.
     *   - 'content' (string) Raw HTML/text content of the tab.
     *
     * Compatible with:
     *   - Generic plugin meta key: _custom_product_tabs
     *   - Yikes plugin meta key  : yikes_woo_products_tabs (also has 'id' key, ignored here)
     *
     * @param array $tabs Array of tab data arrays.
     * @return array Processed HTML content parts.
     */
    private function process_tab_list( $tabs ) {
        $parts = array();

        foreach ( $tabs as $tab ) {
            if ( empty( $tab['content'] ) ) {
                continue;
            }

            $title   = isset( $tab['title'] ) ? $tab['title'] : '';
            $content = $tab['content'];

            if ( function_exists( 'do_shortcode' ) ) {
                $content = do_shortcode( $content );
            }
            if ( function_exists( 'wpautop' ) ) {
                $content = wpautop( $content );
            }

            // If tab title clearly indicates FAQ, wrap in explicit jvm-faq wrapper to ensure extraction.
            if ( $this->is_faq_tab_title( $title ) ) {
                $parts[] = '<div class="jvm-faq">' . $content . '</div>';
            } else {
                $parts[] = "\n\n" . $content . "\n\n";
            }
        }

        return $parts;
    }

    /**
     * Check if a custom tab title indicates FAQ content.
     *
     * @param string $title Tab title.
     * @return bool
     */
    private function is_faq_tab_title( $title ) {
        if ( empty( trim( $title ) ) ) {
            return false;
        }

        return (bool) preg_match(
            '/\b(faq|faqs|q&a|qna|tanya\s*jawab|pertanyaan|frequently\s+asked\s+questions)\b/iu',
            $title
        );
    }

    /* ──────────────────────────────────────────────
     *  Extraction orchestrator
     * ────────────────────────────────────────────── */

    /**
     * Extract FAQs using all available strategies.
     *
     * @param string $content Combined HTML content.
     * @return array Array of ['question' => ..., 'answer' => ...].
     */
    private function extract_faqs( $content ) {
        $faqs = array();

        // 1. Explicit <div class="jvm-faq"> wrappers (always active).
        //    Handles: headings, <li> inline Q&A, and <p><strong>Q</strong><br>A</p> inside wrapper.
        $faqs = array_merge( $faqs, $this->extract_from_jvm_faq_wrapper( $content ) );

        // 2-5. Auto-detect strategies.
        if ( '1' === get_option( 'jvm_schema_faq_autodetect', '1' ) ) {
            $faqs = array_merge( $faqs, $this->extract_from_question_headings( $content ) );
            $faqs = array_merge( $faqs, $this->extract_from_details_elements( $content ) );
            $faqs = array_merge( $faqs, $this->extract_from_list_items( $content ) );
            $faqs = array_merge( $faqs, $this->extract_from_paragraph_items( $content ) );
        }

        return $this->deduplicate( $faqs );
    }

    /* ──────────────────────────────────────────────
     *  Strategy 1: Explicit <div class="jvm-faq">
     * ────────────────────────────────────────────── */

    /**
     * @param string $content HTML content.
     * @return array
     */
    private function extract_from_jvm_faq_wrapper( $content ) {
        $faqs = array();

        if ( preg_match_all( '/<div[^>]*class="[^"]*jvm-faq[^"]*"[^>]*>(.*?)<\/div>/is', $content, $matches ) ) {
            foreach ( $matches[1] as $inner ) {
                // Parse heading → answer pairs (h1-h6 as questions).
                $faqs = array_merge( $faqs, $this->parse_heading_answer_pairs( $inner ) );

                // Also parse <li> inline Q&A inside the wrapper (e.g. <ol>/<ul> with <strong>Q<br>A).
                $faqs = array_merge( $faqs, $this->parse_inline_items( $inner, 'li' ) );

                // Also parse <p> inline Q&A inside the wrapper (e.g. <p><strong>Q</strong><br>A</p>).
                $faqs = array_merge( $faqs, $this->parse_inline_items( $inner, 'p' ) );
            }
        }

        return $faqs;
    }

    /* ──────────────────────────────────────────────
     *  Strategy 2: Headings ending with "?"
     * ────────────────────────────────────────────── */

    /**
     * @param string $content HTML content.
     * @return array
     */
    private function extract_from_question_headings( $content ) {
        $faqs  = array();
        $parts = preg_split( '/(<h[1-6][^>]*>.*?<\/h[1-6]>)/is', $content, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY );

        $current_q = '';
        foreach ( $parts as $part ) {
            if ( preg_match( '/<h[1-6][^>]*>(.*?)<\/h[1-6]>/is', $part, $m ) ) {
                $text = trim( wp_strip_all_tags( $m[1] ) );
                $current_q = $this->is_question_like( $text ) ? $this->normalize_question( $text ) : '';
            } else {
                if ( ! empty( $current_q ) ) {
                    $answer = $this->clean_answer( $part );
                    if ( ! empty( $answer ) ) {
                        $faqs[] = array(
                            'question' => $current_q,
                            'answer'   => $answer,
                        );
                    }
                    $current_q = '';
                }
            }
        }

        return $faqs;
    }

    /* ──────────────────────────────────────────────
     *  Strategy 3: <details>/<summary> accordion
     * ────────────────────────────────────────────── */

    /**
     * @param string $content HTML content.
     * @return array
     */
    private function extract_from_details_elements( $content ) {
        $faqs = array();

        if ( preg_match_all( '/<details[^>]*>\s*<summary[^>]*>(.*?)<\/summary>(.*?)<\/details>/is', $content, $matches, PREG_SET_ORDER ) ) {
            foreach ( $matches as $match ) {
                $question = trim( wp_strip_all_tags( $match[1] ) );
                $answer   = $this->clean_answer( $match[2] );
                if ( ! empty( $question ) && ! empty( $answer ) ) {
                    $faqs[] = array(
                        'question' => $this->normalize_question( $question ),
                        'answer'   => $answer,
                    );
                }
            }
        }

        return $faqs;
    }

    /* ──────────────────────────────────────────────
     *  Strategy 4: Inline Q&A in <li> elements
     *  Handles:
     *    <li><strong>Q?</strong><br>A</li>
     *    <li><p><strong>Q?</strong><br>A</p></li>
     *    <li>Q?<br>A</li>
     * ────────────────────────────────────────────── */

    /**
     * @param string $content HTML content.
     * @return array
     */
    private function extract_from_list_items( $content ) {
        if ( stripos( $content, '<li' ) === false ) {
            return array();
        }

        return $this->parse_inline_items( $content, 'li' );
    }

    /* ──────────────────────────────────────────────
     *  Strategy 5: Inline Q&A in <p> elements
     *  Handles:
     *    <p><strong>Q?</strong><br>A</p>
     *    <p>Q?<br>A</p>
     *
     *  Guards (prevent false positives):
     *    - <p> must contain <strong> AND <br> before processing
     *    - parse_inline_qa() validates is_question_like() + answer length > 10
     * ────────────────────────────────────────────── */

    /**
     * @param string $content HTML content.
     * @return array
     */
    private function extract_from_paragraph_items( $content ) {
        // Quick guard: skip entirely if no <strong> + <br> combo present.
        if ( stripos( $content, '<strong' ) === false || stripos( $content, '<br' ) === false ) {
            return array();
        }

        return $this->parse_inline_items( $content, 'p' );
    }

    /* ──────────────────────────────────────────────
     *  Shared inline item parser (Strategy 4 & 5)
     * ────────────────────────────────────────────── */

    /**
     * Extract inline Q&A pairs from a given tag type using DOMDocument or regex fallback.
     *
     * Supports tag: 'li' (Strategy 4) and 'p' (Strategy 5).
     * For 'p' tags, applies an additional pre-filter: only processes <p> elements
     * that contain both <strong> and <br> to avoid false positives.
     *
     * @param string $content HTML content.
     * @param string $tag     HTML tag name to scan ('li' or 'p').
     * @return array
     */
    private function parse_inline_items( $content, $tag ) {
        if ( class_exists( 'DOMDocument' ) ) {
            return $this->parse_inline_items_dom( $content, $tag );
        }

        return $this->parse_inline_items_regex( $content, $tag );
    }

    /**
     * DOMDocument-based inline item extraction (preferred).
     *
     * @param string $content HTML content.
     * @param string $tag     HTML tag name.
     * @return array
     */
    private function parse_inline_items_dom( $content, $tag ) {
        libxml_use_internal_errors( true );

        $dom = new DOMDocument( '1.0', 'UTF-8' );
        $dom->loadHTML(
            '<?xml encoding="utf-8" ?><body>' . $content . '</body>',
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
        );

        libxml_clear_errors();

        $faqs  = array();
        $items = $dom->getElementsByTagName( $tag );

        foreach ( $items as $node ) {
            $inner_html = '';
            foreach ( $node->childNodes as $child ) {
                $inner_html .= $dom->saveHTML( $child );
            }

            // For <p> tags: pre-filter — must have <strong> and <br> to avoid false positives.
            if ( 'p' === $tag ) {
                if ( stripos( $inner_html, '<strong' ) === false || stripos( $inner_html, '<br' ) === false ) {
                    continue;
                }
            }

            $faq = $this->parse_inline_qa( $inner_html );
            if ( $faq ) {
                $faqs[] = $faq;
            }
        }

        return $faqs;
    }

    /**
     * Regex fallback for inline item extraction (when DOMDocument unavailable).
     *
     * @param string $content HTML content.
     * @param string $tag     HTML tag name.
     * @return array
     */
    private function parse_inline_items_regex( $content, $tag ) {
        $faqs = array();

        if ( preg_match_all( '/<' . $tag . '[^>]*>(.*?)<\/' . $tag . '>/is', $content, $matches ) ) {
            foreach ( $matches[1] as $inner ) {
                // For <p> tags: pre-filter — must have <strong> and <br>.
                if ( 'p' === $tag ) {
                    if ( stripos( $inner, '<strong' ) === false || stripos( $inner, '<br' ) === false ) {
                        continue;
                    }
                }

                $faq = $this->parse_inline_qa( $inner );
                if ( $faq ) {
                    $faqs[] = $faq;
                }
            }
        }

        return $faqs;
    }

    /**
     * Parse a single inline Q&A fragment.
     *
     * Tries three approaches:
     *   A) <strong>Question?</strong> followed by answer
     *   B) Text split by <br> — question before, answer after
     *   C) Plain text split by first "?" boundary
     *
     * @param string $html Inner HTML of a list item or similar block.
     * @return array|null ['question' => ..., 'answer' => ...] or null.
     */
    private function parse_inline_qa( $html ) {
        // Replace <br> tags with a reliable delimiter before stripping.
        $delimited = preg_replace( '/<br[^>]*>/i', '{{QSPLIT}}', $html );

        // ── Approach A: <strong>Q?</strong> ... answer ──
        if ( preg_match( '/<strong[^>]*>(.*?)<\/strong>/is', $delimited, $m ) ) {
            $q_raw = trim( wp_strip_all_tags( $m[1] ) );

            if ( $this->is_question_like( $q_raw ) ) {
                // Everything after </strong> (and optional delimiter) is the answer.
                $after  = preg_replace( '/^.*?<\/strong>\s*(\{\{QSPLIT\}\})?\s*/is', '', $delimited );
                $answer = trim( wp_strip_all_tags( $after ) );

                if ( mb_strlen( $answer ) > 10 ) {
                    return array(
                        'question' => $this->normalize_question( $q_raw ),
                        'answer'   => $answer,
                    );
                }
            }
        }

        // ── Approach B: split by <br> delimiter ──
        $plain = wp_strip_all_tags( $delimited );
        $parts = explode( '{{QSPLIT}}', $plain, 2 );

        if ( count( $parts ) === 2 ) {
            $q = trim( $parts[0] );
            $a = trim( $parts[1] );

            if ( $this->is_question_like( $q ) && mb_strlen( $a ) > 10 ) {
                return array(
                    'question' => $this->normalize_question( $q ),
                    'answer'   => $a,
                );
            }
        }

        // ── Approach C: split by first "?" in plain text ──
        $full = trim( wp_strip_all_tags( $html ) );
        if ( preg_match( '/^(.+?\?)\s+(.{15,})$/us', $full, $m ) ) {
            $q = trim( $m[1] );
            if ( $this->is_question_like( $q ) ) {
                return array(
                    'question' => $this->normalize_question( $q ),
                    'answer'   => trim( $m[2] ),
                );
            }
        }

        return null;
    }

    /* ──────────────────────────────────────────────
     *  Shared helpers
     * ────────────────────────────────────────────── */

    /**
     * Parse heading → answer pairs from an HTML fragment.
     * Used by the explicit jvm-faq wrapper strategy.
     *
     * @param string $html Inner HTML of a wrapper.
     * @return array
     */
    private function parse_heading_answer_pairs( $html ) {
        $faqs  = array();
        $parts = preg_split( '/(<h[1-6][^>]*>.*?<\/h[1-6]>)/is', $html, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY );

        $current_q = '';
        foreach ( $parts as $part ) {
            if ( preg_match( '/<h[1-6][^>]*>(.*?)<\/h[1-6]>/is', $part, $m ) ) {
                $current_q = $this->normalize_question( trim( wp_strip_all_tags( $m[1] ) ) );
            } else {
                if ( ! empty( $current_q ) ) {
                    $answer = $this->clean_answer( $part );
                    if ( ! empty( $answer ) ) {
                        $faqs[] = array(
                            'question' => $current_q,
                            'answer'   => $answer,
                        );
                    }
                    $current_q = '';
                }
            }
        }

        return $faqs;
    }

    /**
     * Clean answer HTML — keep basic formatting tags accepted by Google.
     *
     * @param string $html Raw answer HTML.
     * @return string
     */
    private function clean_answer( $html ) {
        return trim( strip_tags( $html, '<p><a><br><b><strong><i><em><ul><ol><li>' ) );
    }

    /**
     * Normalize question text — strip numbering prefixes and Q: markers.
     *
     * Examples:
     *   "1. Apakah ini bagus?"   → "Apakah ini bagus?"
     *   "2) Berapa harganya?"    → "Berapa harganya?"
     *   "Q: What is this?"      → "What is this?"
     *
     * @param string $text Raw question text.
     * @return string
     */
    private function normalize_question( $text ) {
        $text = trim( $text );
        // Strip "1.", "2)", "3 -" etc.
        $text = preg_replace( '/^\d+[\.\)\-]\s*/u', '', $text );
        // Strip "Q:", "Question:", "Pertanyaan:" prefix.
        $text = preg_replace( '/^(Q|Question|Pertanyaan)\s*[:\-]\s*/iu', '', $text );
        return trim( $text );
    }

    /**
     * Check if text looks like a question.
     *
     * @param string $text Plain text (tags already stripped).
     * @return bool
     */
    private function is_question_like( $text ) {
        $text = $this->normalize_question( $text );

        if ( $text === '' ) {
            return false;
        }

        // Ends with "?"
        if ( mb_substr( $text, -1 ) === '?' ) {
            return true;
        }

        // Starts with interrogative word (ID + EN).
        return (bool) preg_match(
            '/^(apa|apakah|bagaimana|kenapa|mengapa|kapan|di\s?mana|dimana|siapa|berapa|dapatkah|bisakah|bolehkah|perlukah|haruskah|seberapa|untuk\s+apa|what|why|when|where|who|how|can|could|should|is|are|do|does)\b/iu',
            $text
        );
    }

    /**
     * Remove duplicate questions (case-insensitive).
     *
     * @param array $faqs Array of FAQ pairs.
     * @return array
     */
    private function deduplicate( $faqs ) {
        $seen   = array();
        $unique = array();

        foreach ( $faqs as $faq ) {
            $key = mb_strtolower( $faq['question'] );
            if ( ! isset( $seen[ $key ] ) ) {
                $seen[ $key ] = true;
                $unique[]     = $faq;
            }
        }

        return $unique;
    }
}
