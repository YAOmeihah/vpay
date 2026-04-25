<?php
declare(strict_types=1);

namespace tests;

use PHPUnit\Framework\TestCase;

class PayPageStaticAssetsTest extends TestCase
{
    private string $payHtml;
    private string $payCss;
    private string $rootPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->rootPath = dirname(__DIR__);
        $this->payHtml = file_get_contents($this->rootPath . '/public/payPage/pay.html') ?: '';
        $this->payCss = file_get_contents($this->rootPath . '/public/payPage/pay.css') ?: '';
    }

    public function test_pay_page_uses_non_conflicting_countdown_function_name(): void
    {
        $this->assertStringContainsString('function startCountdown(', $this->payHtml);
        $this->assertStringNotContainsString('function timer(', $this->payHtml);
        $this->assertStringContainsString('startCountdown(remainingSeconds);', $this->payHtml);
    }

    public function test_pay_page_exposes_new_responsive_layout_shell(): void
    {
        $this->assertStringContainsString('class="payment-shell"', $this->payHtml);
        $this->assertStringContainsString('class="payment-card"', $this->payHtml);
        $this->assertStringContainsString('class="payment-main"', $this->payHtml);
        $this->assertStringContainsString('class="payment-side"', $this->payHtml);
    }

    public function test_pay_page_exposes_pending_choice_payment_type_shell(): void
    {
        $this->assertStringContainsString('class="payment-choice"', $this->payHtml);
        $this->assertStringContainsString('availablePayTypes', $this->payHtml);
        $this->assertStringContainsString('choiceSubmittingType', $this->payHtml);
        $this->assertStringContainsString('selectPayType(option.type)', $this->payHtml);
        $this->assertStringContainsString("../selectOrderPayType", $this->payHtml);
        $this->assertStringContainsString("assignStatus === 'pending_choice'", $this->payHtml);
    }

    public function test_pay_page_choice_css_has_responsive_payment_type_actions(): void
    {
        $this->assertStringContainsString('.payment-choice', $this->payCss);
        $this->assertStringContainsString('.payment-choice-actions', $this->payCss);
        $this->assertStringContainsString('.payment-choice-btn', $this->payCss);
        $this->assertStringContainsString('.payment-choice-btn.loading::after', $this->payCss);
    }

    public function test_desktop_layout_hides_mobile_save_actions(): void
    {
        $this->assertStringContainsString('@media (min-width: 769px)', $this->payCss);
        $this->assertStringContainsString('.mobile-save-container', $this->payCss);
        $this->assertStringContainsString('display: none !important;', $this->payCss);
    }

    public function test_mobile_layout_keeps_amount_time_and_warning_ahead_of_qr_section(): void
    {
        $screenPos = strpos($this->payHtml, 'class="mobile-first-screen"');
        $summaryPos = strpos($this->payHtml, 'class="mobile-summary"');
        $warningPos = strpos($this->payHtml, 'class="summary-card amount-warning-card"');
        $qrPos = strpos($this->payHtml, 'class="payment-qr-section"');

        $this->assertNotFalse($screenPos);
        $this->assertNotFalse($summaryPos);
        $this->assertNotFalse($warningPos);
        $this->assertNotFalse($qrPos);
        $this->assertLessThan($qrPos, $screenPos);
        $this->assertLessThan($qrPos, $summaryPos);
        $this->assertLessThan($qrPos, $warningPos);
    }

    public function test_mobile_css_has_compact_summary_and_warning_rules(): void
    {
        $this->assertStringContainsString('.mobile-first-screen', $this->payCss);
        $this->assertStringContainsString('.mobile-summary', $this->payCss);
        $this->assertStringContainsString('.amount-warning-card', $this->payCss);
        $this->assertStringContainsString('@media (max-width: 768px)', $this->payCss);
        $this->assertStringContainsString('grid-template-columns: 1fr 1fr;', $this->payCss);
        $this->assertStringContainsString('@media (min-width: 769px)', $this->payCss);
    }

    public function test_mobile_header_has_dedicated_copy_stack_and_security_badge(): void
    {
        $this->assertStringContainsString('class="payment-header-mobile"', $this->payHtml);
        $this->assertStringContainsString('.payment-header-copy', $this->payCss);
        $this->assertStringContainsString('.payment-header-mobile', $this->payCss);
        $this->assertStringContainsString('.payment-brand-desktop', $this->payCss);
        $this->assertStringContainsString('.payment-header-mobile-top', $this->payCss);
    }

    public function test_timeout_banner_and_modals_use_card_spacing_hooks(): void
    {
        $this->assertStringContainsString('margin: 24px 32px 24px;', $this->payCss);
        $this->assertStringContainsString('class="payment-modal-content payment-modal-card"', $this->payHtml);
        $this->assertStringContainsString('class="success-modal-content status-modal-card"', $this->payHtml);
        $this->assertStringContainsString('class="check-result-content status-modal-card"', $this->payHtml);
        $this->assertStringContainsString('.modal-eyebrow', $this->payCss);
        $this->assertStringContainsString('.status-modal-orb', $this->payCss);
    }

    public function test_modal_cards_avoid_decorative_top_bar_and_confirmation_orb(): void
    {
        $this->assertStringNotContainsString('status-modal-orb subtle', $this->payHtml);
        $this->assertStringNotContainsString('.payment-modal-card::before', $this->payCss);
        $this->assertStringNotContainsString('.status-modal-card::before', $this->payCss);
    }

    public function test_status_modal_icons_do_not_use_extra_ring_pseudo_elements(): void
    {
        $this->assertStringNotContainsString('.status-modal-orb::after', $this->payCss);
        $this->assertStringNotContainsString('.check-result-icon.loading::before', $this->payCss);
    }

    public function test_loading_status_icon_keeps_animation_without_extra_rings(): void
    {
        $this->assertStringContainsString('.check-result-icon.loading', $this->payCss);
        $this->assertStringContainsString('loading-icon-spin', $this->payCss);
        $this->assertStringContainsString('status-orb-pulse', $this->payCss);
    }

    public function test_status_modal_uses_svg_icons_and_line_spinner(): void
    {
        $this->assertStringContainsString('status-icon-glyph', $this->payHtml);
        $this->assertStringContainsString('status-icon-spinner', $this->payHtml);
        $this->assertStringContainsString('renderStatusIcon(type)', $this->payHtml);
        $this->assertStringContainsString('.status-icon-spinner', $this->payCss);
        $this->assertStringContainsString('stroke-dasharray', $this->payCss);
    }

    public function test_error_status_icon_uses_shield_glyph(): void
    {
        $this->assertStringContainsString('<path d="M24 12l11 4v8c0 7.2-4.7 12.9-11 15-6.3-2.1-11-7.8-11-15v-8l11-4z"></path>', $this->payHtml);
        $this->assertStringContainsString('.check-result-icon.error .status-icon-glyph', $this->payCss);
        $this->assertStringContainsString('width: 40px;', $this->payCss);
    }

    public function test_repo_no_longer_keeps_or_references_legacy_go_alipay_page(): void
    {
        $legacyFile = $this->rootPath . '/public/payPage/go_alipay.html';
        $this->assertFileDoesNotExist($legacyFile);

        $extensions = ['php', 'html', 'js', 'ts', 'vue', 'css', 'scss', 'md', 'json'];
        $excludedDirs = [
            $this->rootPath . '/.git',
            $this->rootPath . '/.worktrees',
            $this->rootPath . '/vendor',
            $this->rootPath . '/node_modules',
            $this->rootPath . '/runtime',
            $this->rootPath . '/test-results',
            $this->rootPath . '/public/console/static',
        ];
        $excludedFiles = [
            str_replace('\\', '/', __FILE__),
        ];

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($this->rootPath, \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            $path = $file->getPathname();

            if (!$file->isFile()) {
                continue;
            }

            $normalizedPath = str_replace('\\', '/', $path);

            foreach ($excludedDirs as $excludedDir) {
                $normalizedExcludedDir = str_replace('\\', '/', $excludedDir);
                if (str_starts_with($normalizedPath, $normalizedExcludedDir . '/')) {
                    continue 2;
                }
            }

            if (in_array($normalizedPath, $excludedFiles, true)) {
                continue;
            }

            if (!in_array(strtolower($file->getExtension()), $extensions, true)) {
                continue;
            }

            $contents = file_get_contents($path);
            $this->assertIsString($contents);
            $this->assertStringNotContainsString('go_alipay', $contents, 'Legacy go_alipay reference found in ' . $normalizedPath);
        }
    }
}
