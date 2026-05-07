<?php
/**
 * FAQPage schema generator — explicit wrapper + smart auto-detection.
 *
 * Detection strategies (in order):
 *   1. Explicit  <div class="jvm-faq"> wrappers
 *   2. Headings (h1-h6) with question-like text, followed by content
 *   3. <details>/<summary> accordion pattern
 *   4. Inline Q&A in list items (<li>) — via DOMDocument
 *
 * Content sources:
 *   - Article / blog post: post_content
 *   - WooCommerce product: post_content + custom tab contents (_custom_product_tabs)
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
        if ( function_exists( 'is_product' ) && is_product() ) {
            $custom_tabs = get_post_meta( $post->ID, '_custom_product_tabs', true );
            if ( is_array( $custom_tabs ) ) {
                foreach ( $custom_tabs as $tab ) {
                    if ( ! empty( $tab['content'] ) ) {
                        $parts[] = $tab['content'];
                    }
                }
            }
        }

        return implode( "\n", array_filter( $parts ) );
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
        $faqs = array_merge( $faqs, $this->extract_from_jvm_faq_wrapper( $content ) );

        // 2-4. Auto-detect strategies.
        if ( '1' === get_option( 'jvm_schema_faq_autodetect', '1' ) ) {
            $faqs = array_merge( $faqs, $this->extract_from_question_headings( $content ) );
            $faqs = array_merge( $faqs, $this->extract_from_details_elements( $content ) );
            $faqs = array_merge( $faqs, $this->extract_from_list_items( $content ) );
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
                $faqs = array_merge( $faqs, $this->parse_heading_answer_pairs( $inner ) );
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
     *    <li><p><strong>Q?</strong><br>A</p></li>
     *    <li><p>Q?<br>A</p></li>
     * ────────────────────────────────────────────── */

    /**
     * @param string $content HTML content.
     * @return array
     */
    private function extract_from_list_items( $content ) {
        if ( stripos( $content, '<li' ) === false ) {
            return array();
        }

        if ( class_exists( 'DOMDocument' ) ) {
            return $this->extract_list_items_dom( $content );
        }

        return $this->extract_list_items_regex( $content );
    }

    /**
     * DOMDocument-based list item extraction (preferred).
     *
     * @param string $content HTML content.
     * @return array
     */
    private function extract_list_items_dom( $content ) {
        libxml_use_internal_errors( true );

        $dom = new DOMDocument( '1.0', 'UTF-8' );
        $dom->loadHTML(
            '<?xml encoding="utf-8" ?><body>' . $content . '</body>',
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
        );

        libxml_clear_errors();

        $faqs  = array();
        $items = $dom->getElementsByTagName( 'li' );

        foreach ( $items as $li ) {
            $inner_html = '';
            foreach ( $li->childNodes as $child ) {
                $inner_html .= $dom->saveHTML( $child );
            }

            $faq = $this->parse_inline_qa( $inner_html );
            if ( $faq ) {
                $faqs[] = $faq;
            }
        }

        return $faqs;
    }

    /**
     * Regex fallback for list item extraction (when DOMDocument unavailable).
     *
     * @param string $content HTML content.
     * @return array
     */
    private function extract_list_items_regex( $content ) {
        $faqs = array();

        if ( preg_match_all( '/<li[^>]*>(.*?)<\/li>/is', $content, $matches ) ) {
            foreach ( $matches[1] as $inner ) {
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
