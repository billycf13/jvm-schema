<?php
/**
 * JVM Schema — Settings page view.
 *
 * @package JVM_Schema
 * @var array  $tabs        Available tabs.
 * @var string $current_tab Active tab slug.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$page_map = array(
    'general'      => 'jvm-schema-general',
    'website'      => 'jvm-schema-website',
    'webpage'      => 'jvm-schema-webpage',
    'organization' => 'jvm-schema-organization',
    'breadcrumb'   => 'jvm-schema-breadcrumb',
    'product'      => 'jvm-schema-product',
    'article'      => 'jvm-schema-article',
    'faq'          => 'jvm-schema-faq',
);
$settings_page = isset( $page_map[ $current_tab ] ) ? $page_map[ $current_tab ] : 'jvm-schema-general';
?>
<div class="wrap jvm-schema-wrap">
    <h1><?php echo esc_html( get_admin_page_title() ); ?></h1>

    <nav class="jvm-tabs">
        <?php foreach ( $tabs as $slug => $label ) : ?>
            <a
                href="<?php echo esc_url( admin_url( 'admin.php?page=jvm-schema&tab=' . $slug ) ); ?>"
                class="jvm-tabs__link <?php echo $current_tab === $slug ? 'jvm-tabs__link--active' : ''; ?>"
            >
                <?php echo esc_html( $label ); ?>
            </a>
        <?php endforeach; ?>
    </nav>

    <form method="post" action="options.php">
        <input type="hidden" name="jvm_schema_current_tab" value="<?php echo esc_attr( $current_tab ); ?>" />
        <?php
        settings_fields( $settings_page );
        do_settings_sections( $settings_page );
        submit_button();
        ?>
    </form>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // ── Conditional Visibility ──────────────────────────────
    
    function toggleRows(selector, show) {
        document.querySelectorAll(selector).forEach(function(row) {
            row.style.display = show ? '' : 'none';
        });
    }

    function initOrganizationLogic() {
        // Business Type
        const bizTypeRadios = document.querySelectorAll('input[name="jvm_schema_business_type"]');
        const handleBizType = () => {
            const val = document.querySelector('input[name="jvm_schema_business_type"]:checked').value;
            toggleRows('.jvm-row-localbusiness', val === 'localbusiness');
            if (val === 'localbusiness') handleHoursEnable();
        };
        bizTypeRadios.forEach(r => r.addEventListener('change', handleBizType));
        if (bizTypeRadios.length) handleBizType();

        // Logo Source
        const logoSourceRadios = document.querySelectorAll('input[name="jvm_schema_org_logo_source"]');
        const handleLogoSource = () => {
            const val = document.querySelector('input[name="jvm_schema_org_logo_source"]:checked').value;
            toggleRows('.jvm-row-logo-media', val === 'media');
            toggleRows('.jvm-row-logo-url', val === 'url');
        };
        logoSourceRadios.forEach(r => r.addEventListener('change', handleLogoSource));
        if (logoSourceRadios.length) handleLogoSource();

        // Hours Enable
        const hoursToggle = document.querySelector('input[name="jvm_schema_hours_enable"]');
        const handleHoursEnable = () => {
            const bizVal = document.querySelector('input[name="jvm_schema_business_type"]:checked').value;
            const enabled = hoursToggle.checked && bizVal === 'localbusiness';
            toggleRows('.jvm-row-hours', enabled);
            if (enabled) handleHoursMode();
            else {
                toggleRows('.jvm-row-hours-perday', false);
                toggleRows('.jvm-row-hours-pattern', false);
            }
        };
        if (hoursToggle) {
            hoursToggle.addEventListener('change', handleHoursEnable);
            handleHoursEnable();
        }

        // Hours Mode
        const hoursModeRadios = document.querySelectorAll('input[name="jvm_schema_hours_mode"]');
        const handleHoursMode = () => {
            const bizVal = document.querySelector('input[name="jvm_schema_business_type"]:checked').value;
            const hoursVal = hoursToggle.checked;
            if (!hoursVal || bizVal !== 'localbusiness') return;

            const val = document.querySelector('input[name="jvm_schema_hours_mode"]:checked').value;
            toggleRows('.jvm-row-hours-perday', val === 'perday');
            toggleRows('.jvm-row-hours-pattern', val === 'pattern');
        };
        hoursModeRadios.forEach(r => r.addEventListener('change', handleHoursMode));
        if (hoursModeRadios.length) handleHoursMode();

        // Output Location
        const outputRadios = document.querySelectorAll('input[name="jvm_schema_org_output"]');
        const handleOutput = () => {
            const val = document.querySelector('input[name="jvm_schema_org_output"]:checked').value;
            toggleRows('.jvm-row-output-specific', val === 'specific');
        };
        outputRadios.forEach(r => r.addEventListener('change', handleOutput));
        if (outputRadios.length) handleOutput();
    }

    // ── Media Uploader ──────────────────────────────────────
    
    function initMediaUploader() {
        const uploadBtn = document.querySelector('.jvm-upload-button');
        const removeBtn = document.querySelector('.jvm-remove-button');
        const previewDiv = document.querySelector('.jvm-logo-preview');
        
        if (!uploadBtn) return;

        let frame;
        uploadBtn.addEventListener('click', function(e) {
            e.preventDefault();
            if (frame) { frame.open(); return; }
            
            frame = wp.media({
                title: 'Select Logo',
                button: { text: 'Use this image' },
                multiple: false
            });

            frame.on('select', function() {
                const attachment = frame.state().get('selection').first().toJSON();
                document.querySelector(uploadBtn.dataset.input).value = attachment.id;
                previewDiv.innerHTML = '<img src="' + attachment.url + '" style="max-width:150px;max-height:150px;display:block;border:1px solid #ccc;padding:5px;" />';
                removeBtn.classList.remove('hidden');
            });

            frame.open();
        });

        removeBtn.addEventListener('click', function() {
            document.querySelector(uploadBtn.dataset.input).value = '0';
            previewDiv.innerHTML = '';
            removeBtn.classList.add('hidden');
        });
    }

    if (document.querySelector('.jvm-schema-wrap')) {
        initOrganizationLogic();
        initMediaUploader();
    }
});
</script>
