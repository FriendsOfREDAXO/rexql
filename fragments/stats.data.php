<?php

use FriendsOfRedaxo\RexQL\Utility;

/**
 * @var rex_fragment $this
 */

$name = $this->getVar('name', '');
$icon = $this->getVar('icon', '');
$cols = $this->getVar('cols', 3);
$data = $this->getVar('data', []);
?>
<div class="col-sm-<?= $cols ?>">
  <h3><?= ($icon ? '<i class="' . $icon . '"></i> ' : '') . $name; ?></h3>
  <?php if (empty($data) || (empty($data['stats']) && empty($data['queries']))) : ?>
    <p class="rexql-empty"><?php echo rex_i18n::msg('rexql_stats_no_data'); ?></p>
  <?php endif; ?>
  <?php if (isset($data['stats']) && is_array($data['stats'])) : ?>
    <dl class="rexql-simple-table <?= $data['class'] ?? '' ?>">
      <?php foreach ($data['stats'] as $key => $stats) :
        $icon = isset($stats['icon']) ? '<i class="' . $stats['icon'] . '"></i> ' : '';
        $type = $stats['type'] ?? 'int';
        if (isset($stats['value'])) {
          switch ($type) {
            case 'int':
              $value = $stats['value'] ? (int)$stats['value'] : 0;
              break;
            case 'float':
              $value = $stats['value'] ? (float)$stats['value'] : 0.0;
              break;
            case 'string':
              $value = $stats['value'] ? $stats['value'] : '';
              break;
            case 'ms':
              $value = $stats['value'] ? round((float)$stats['value'], 2) . ' ms' : '0 ms';
              break;
            case 'bytes':
              $value = $stats['value'] ? rex_formatter::bytes((int)$stats['value']) : '0 B';
              break;
            default:
              $value = $stats['value'] ? $stats['value'] : '';
          }
        }
        $label = isset($stats['label']) ? $stats['label'] : $key;
        $class = isset($stats['class']) ? $stats['class'] : '';
      ?>
        <dt><?php echo $icon . $label; ?>:</dt>
        <?php if (isset($stats['value'])): ?><dd class="<?php echo $class; ?>"><?= $value ?? '' ?></dd><?php endif; ?>
      <?php endforeach; ?>
    </dl>
  <?php endif; ?>
  <?php if (isset($data['queries']) && is_array($data['queries'])) : ?>
    <ol class="rexql-query-list">
      <?php foreach ($data['queries'] as $query) :
        $key = $query['name'] ?? '[PUBLIC]';
        $formattedQuery = Utility::formatGraphQLQuery($query['query'] ?? '');
        $dateTime = isset($query['createdate']) ? rex_formatter::date($query['createdate'], 'd.m.Y H:i:s') : '';
      ?>
        <li class="rexql-query-item">
          <pre style="margin:0"><code><?= $formattedQuery; ?></code></pre>
          <small class="rexql-query-meta">
            <span class="rexql-query-meta-item">
              <i class="fa fa-user" title="<?= rex_i18n::msg('rexql_permissions_api_key') ?>"></i>
              <?= $key; ?>
            </span>
            <span class="rexql-query-meta-item">
              <i class="fa fa-clock" title="<?= rex_i18n::msg('rexql_stats_execution_time') ?>"></i>
              <?= $query['execution_time']  ?? '0'; ?> ms
            </span>
            <span class="rexql-query-meta-item">
              <i class="fa fa-memory" title="<?= rex_i18n::msg('rexql_stats_memory_usage') ?>"></i>
              <?= rex_formatter::bytes($query['memory_usage'] ?? 0); ?>
            </span>
            <span class="rexql-query-meta-item">
              <i class="fa fa-calendar" title="<?= rex_i18n::msg('rexql_permissions_created') ?>"></i>
              <?= $dateTime ?>
            </span>
          </small>
        </li>
      <?php endforeach; ?>
    </ol>
  <?php endif; ?>
</div>