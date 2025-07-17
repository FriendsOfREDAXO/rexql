<?php

/**
 * rexQL Hauptseite
 */

echo '<div class="rex-page-rexql">';
echo rex_view::title(rex_i18n::msg('rexql_title'));

// Unterseiten über REDAXO Controller laden
rex_be_controller::includeCurrentPageSubPath();

echo '</div>';

$scriptUrl = $this->getAssetsUrl('rexql.js');
$path = rex_path::frontend(rex_path::absolute($scriptUrl));
$mtime = @filemtime($path);
$scriptUrl .= $mtime ? '?buster=' . $mtime : '';

$api = new \FriendsOfRedaxo\RexQL\Api($this, true);
$schemaFilepath = $this->getCachePath('generated.schema.graphql');
$sdl = \FriendsOfRedaxo\RexQL\Api::loadSdlFile($schemaFilepath);
?>
<script nonce="<?= rex_response::getNonce() ?>">
  var schema = `<?= $sdl ?>`;
  $(document).on('rex:ready', function() {
    const script = document.createElement('script')
    script.type = 'module'
    script.setAttribute('nonce', '<?= rex_response::getNonce() ?>');
    script.src = '<?= $scriptUrl ?>' // output from Vite
    // script.onload = () => {
    //   window.heavyModule.init() // assuming it exposes global API
    // }
    document.head.appendChild(script)
  });
</script>