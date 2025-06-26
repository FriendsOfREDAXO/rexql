<?php

/**
 * rexQL Hauptseite
 */

echo '<div class="rex-page-rexql">';
echo rex_view::title(rex_i18n::msg('rexql_title'));

// Unterseiten über REDAXO Controller laden
rex_be_controller::includeCurrentPageSubPath();

echo '</div>';
