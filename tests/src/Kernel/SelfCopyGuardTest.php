<?php

declare(strict_types=1);

namespace Drupal\Tests\solo_copy_blocks\Kernel;

use Drupal\block\Entity\Block;
use Drupal\KernelTests\KernelTestBase;
use Drupal\solo_copy_blocks\Form\SoloCopyBlocksForm;
use Drupal\Core\Form\FormState;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Copying blocks must never delete the Solo layout with nothing to replace it.
 *
 * WHY THIS EXISTS. Releases 1.0.0 to 1.0.3 deleted every block in the solo
 * theme BEFORE loading the source theme's blocks. With Solo as the default
 * theme the source and the target were the same theme, so the copy found
 * nothing and the site lost its whole block layout: measured on sts.local,
 * 61 blocks became 0. The same happened with an empty source theme.
 *
 * WHAT THIS ENFORCES. Submitting the real form with Solo as the default theme
 * or with an empty default theme leaves Solo's blocks exactly as they were,
 * and the real migration (another theme into Solo) still replaces Solo's
 * blocks with the copies.
 *
 * Red-proofed against the 1.0.x-dev .module at 3c5c510: the self-copy and the
 * empty-source tests fail there (blocks deleted), the migration test passes.
 *
 * @group solo_copy_blocks
 *
 * @license SPDX-License-Identifier: GPL-2.0-or-later
 */
#[Group('solo_copy_blocks')]
#[RunTestsInSeparateProcesses]
class SelfCopyGuardTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'block',
    'user',
    // Solo's optional blocks carry request_path visibility, which needs it.
    'path_alias',
    'solo_copy_blocks',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installConfig(['system']);
    $this->container->get('theme_installer')->install(['stark', 'solo']);
    // Start from a known state: no block in either theme, whatever the themes
    // place on install. Each test creates exactly the blocks it counts.
    foreach (array_merge($this->blockIds('solo'), $this->blockIds('stark')) as $id) {
      Block::load($id)?->delete();
    }
  }

  /**
   * Solo as the default theme: Solo keeps every block and nothing is copied.
   */
  public function testSelfCopyKeepsSoloBlocks(): void {
    $this->setDefaultTheme('solo');
    $this->placeBlock('solo', 'solo_first', 0, TRUE);
    $this->placeBlock('solo', 'solo_second', 1, TRUE);
    $this->placeBlock('solo', 'solo_third', 2, FALSE);
    $before = $this->blockIds('solo');
    $this->assertCount(3, $before);

    $this->submitCopyForm();

    $this->assertSame($before, $this->blockIds('solo'), 'Self-copy must not delete or add any Solo block.');
    $this->assertErrorContains('is the same as the target theme');
  }

  /**
   * An empty default theme: Solo keeps every block and nothing is copied.
   */
  public function testEmptySourceLeavesTargetAlone(): void {
    $this->setDefaultTheme('stark');
    $this->placeBlock('solo', 'solo_first', 0, TRUE);
    $this->placeBlock('solo', 'solo_second', 1, TRUE);
    $before = $this->blockIds('solo');
    $this->assertSame([], $this->blockIds('stark'));

    $this->submitCopyForm();

    $this->assertSame($before, $this->blockIds('solo'), 'An empty source must leave Solo untouched.');
    $this->assertErrorContains('has no blocks');
  }

  /**
   * Regression pin: copying from another theme still replaces Solo's blocks.
   */
  public function testCopyFromOtherThemeStillWorks(): void {
    $this->setDefaultTheme('stark');
    $this->placeBlock('solo', 'solo_old', 0, TRUE);
    $this->placeBlock('stark', 'stark_alpha', 5, TRUE);
    $this->placeBlock('stark', 'stark_beta', -3, FALSE);

    $this->submitCopyForm();

    $this->assertSame(['solo_alpha', 'solo_beta'], $this->blockIds('solo'));
    $alpha = Block::load('solo_alpha');
    $beta = Block::load('solo_beta');
    $this->assertNotNull($alpha);
    $this->assertNotNull($beta);
    $this->assertSame(5, (int) $alpha->getWeight());
    $this->assertTrue($alpha->status());
    $this->assertSame(-3, (int) $beta->getWeight());
    $this->assertFalse($beta->status());
    $this->assertSame('content', $alpha->getRegion());
    // The source theme is never modified.
    $this->assertSame(['stark_alpha', 'stark_beta'], $this->blockIds('stark'));
    $this->assertSame([], $this->container->get('messenger')->messagesByType('error'));
  }

  /**
   * Submits the module's own form, exactly as the admin page does.
   */
  private function submitCopyForm(): void {
    $form_state = new FormState();
    $this->container->get('form_builder')->submitForm(SoloCopyBlocksForm::class, $form_state);
    $this->assertSame([], $form_state->getErrors());
  }

  /**
   * Sets the site default theme.
   */
  private function setDefaultTheme(string $theme): void {
    $this->config('system.theme')->set('default', $theme)->save();
  }

  /**
   * Places a "Powered by Drupal" block in the content region of a theme.
   */
  private function placeBlock(string $theme, string $id, int $weight, bool $status): void {
    Block::create([
      'id' => $id,
      'theme' => $theme,
      'region' => 'content',
      'plugin' => 'system_powered_by_block',
      'weight' => $weight,
      'status' => $status,
    ])->save();
  }

  /**
   * Returns the sorted block IDs of a theme.
   *
   * @return list<string>
   *   The block IDs.
   */
  private function blockIds(string $theme): array {
    $ids = array_keys($this->container->get('entity_type.manager')
      ->getStorage('block')
      ->loadByProperties(['theme' => $theme]));
    sort($ids);
    return $ids;
  }

  /**
   * Asserts that an error message containing a phrase was shown.
   */
  private function assertErrorContains(string $phrase): void {
    $errors = array_map('strval', $this->container->get('messenger')->messagesByType('error'));
    $this->assertNotEmpty($errors, 'An error message must explain why nothing was copied.');
    $this->assertStringContainsString($phrase, implode("\n", $errors));
  }

}
