<?php
/**
 * FAQPage schema generator via content auto-detection.
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
        if ( ! $post || empty( $post->post_content ) ) {
            return null;
        }

        $faqs = $this->extract_faqs_from_content( $post->post_content );

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

    /**
     * Parse content to find <div class="jvm-faq"> and extract Q&A.
     *
     * @param string $content Post content.
     * @return array
     */
    private function extract_faqs_from_content( $content ) {
        $faqs = array();

        // 1. Cari wrapper <div class="jvm-faq">...</div>
        // Menggunakan regex untuk menangkap isi di dalam div dengan class jvm-faq.
        if ( preg_match( '/<div[^>]*class="[^"]*jvm-faq[^"]*"[^>]*>(.*?)<\/div>/is', $content, $match ) ) {
            $inner_content = $match[1];

            // 2. Pecah konten berdasarkan heading tags (h1-h6) sebagai pemisah pertanyaan.
            // Regex ini memecah konten menjadi bagian-bagian yang dimulai dengan <hX>...</hX>
            $parts = preg_split( '/(<h[1-6][^>]*>.*?<\/h[1-6]>)/is', $inner_content, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY );

            $current_q = '';
            foreach ( $parts as $part ) {
                // Jika bagian ini adalah Heading, jadikan Question.
                if ( preg_match( '/<h[1-6][^>]*>(.*?)<\/h[1-6]>/is', $part, $q_match ) ) {
                    $current_q = wp_strip_all_tags( $q_match[1] );
                } else {
                    // Jika bukan heading, maka ini adalah Answer untuk pertanyaan sebelumnya.
                    if ( ! empty( $current_q ) ) {
                        $answer = trim( wp_strip_all_tags( $part, '<p><a><br><b><strong><i><em>' ) );
                        if ( ! empty( $answer ) ) {
                            $faqs[] = array(
                                'question' => $current_q,
                                'answer'   => $answer,
                            );
                        }
                        $current_q = ''; // Reset buat nunggu heading berikutnya.
                    }
                }
            }
        }

        return $faqs;
    }
}
