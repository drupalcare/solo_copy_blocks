<?php

namespace Drupal\solo_copy_blocks\Hook;

use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Hook\Attribute\Hook;

/**
 * Hook implementations for solo_copy_blocks.
 */
class SoloCopyBlocksHooks {

  /**
   * Implements hook_help().
   */
  #[Hook('help')]
  public function help($route_name, RouteMatchInterface $route_match) {
    if ($route_name === 'help.page.solo_copy_blocks') {
      return _solo_copy_blocks_helper_render_readme();
    }
    return NULL;
  }

}
