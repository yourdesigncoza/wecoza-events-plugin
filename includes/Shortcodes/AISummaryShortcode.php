<?php
declare(strict_types=1);

namespace WeCozaEvents\Shortcodes;

use RuntimeException;
use WeCozaEvents\Services\AISummaryDisplayService;
use WeCozaEvents\Support\WordPressRequest;
use WeCozaEvents\Views\Presenters\AISummaryPresenter;
use WeCozaEvents\Views\TemplateRenderer;

use function absint;
use function add_shortcode;
use function esc_html;
use function esc_html__;
use function shortcode_atts;
use function sprintf;
use function strtolower;
use function trim;
use function uniqid;

final class AISummaryShortcode
{
    private const DEFAULT_LIMIT = 20;
    private const LAYOUT_CARD = 'card';
    private const LAYOUT_TIMELINE = 'timeline';

    private AISummaryDisplayService $service;
    private AISummaryPresenter $presenter;
    private TemplateRenderer $renderer;
    private WordPressRequest $request;
    private bool $assetsPrinted = false;

    public function __construct(
        ?AISummaryDisplayService $service = null,
        ?AISummaryPresenter $presenter = null,
        ?TemplateRenderer $renderer = null,
        ?WordPressRequest $request = null
    ) {
        $this->service = $service ?? new AISummaryDisplayService();
        $this->presenter = $presenter ?? new AISummaryPresenter();
        $this->renderer = $renderer ?? new TemplateRenderer();
        $this->request = $request ?? new WordPressRequest();
    }

    public static function register(?self $shortcode = null): void
    {
        $instance = $shortcode ?? new self();
        add_shortcode('wecoza_insert_update_ai_summary', [$instance, 'render']);
    }

    public function render(array $atts = [], string $content = '', string $tag = ''): string
    {
        $atts = shortcode_atts([
            'limit' => self::DEFAULT_LIMIT,
            'layout' => self::LAYOUT_CARD,
            'class_id' => null,
            'operation' => null,
        ], $atts, $tag);

        $limit = absint($atts['limit']);
        if ($limit <= 0) {
            $limit = self::DEFAULT_LIMIT;
        }

        $layout = strtolower(trim($atts['layout']));
        if (!in_array($layout, [self::LAYOUT_CARD, self::LAYOUT_TIMELINE], true)) {
            $layout = self::LAYOUT_CARD;
        }

        $classId = $atts['class_id'] !== null ? absint($atts['class_id']) : null;
        if ($classId !== null && $classId <= 0) {
            $classId = null;
        }

        $operation = $atts['operation'] !== null ? strtoupper(trim($atts['operation'])) : null;
        if ($operation !== null && !in_array($operation, ['INSERT', 'UPDATE'], true)) {
            $operation = null;
        }

        try {
            $records = $this->service->getSummaries($limit, $classId, $operation);
        } catch (RuntimeException $exception) {
            return $this->wrapMessage(
                sprintf(
                    esc_html__('Unable to load AI summaries: %s', 'wecoza-events'),
                    esc_html($exception->getMessage())
                )
            );
        }

        if ($records === []) {
            return $this->wrapMessage(esc_html__('No AI summaries available.', 'wecoza-events'));
        }

        $summaries = $this->presenter->present($records);
        $instanceId = uniqid('wecoza-ai-summary-');

        return $this->renderer->render('ai-summary/main', [
            'assets' => $this->getAssets(),
            'summaries' => $summaries,
            'layout' => $layout,
            'instanceId' => $instanceId,
            'searchInputId' => $instanceId . '-search',
            'operationFilterId' => $instanceId . '-operation',
            'classIdFilter' => $classId,
            'operationFilter' => $operation,
        ]);
    }

    private function wrapMessage(string $message): string
    {
        return '<div class="alert alert-warning" role="alert">' . $message . '</div>';
    }

    private function getAssets(): string
    {
        if ($this->assetsPrinted) {
            return '';
        }

        $this->assetsPrinted = true;

        ob_start();
        ?>
        <script>
            (function() {
                function normaliseSearch(value) {
                    return String(value || '')
                        .toLowerCase()
                        .replace(/\s+/g, ' ')
                        .trim();
                }

                function applyFilters(container) {
                    if (!container) {
                        return;
                    }

                    var searchInput = container.querySelector('[data-role="ai-search"]');
                    var operationSelect = container.querySelector('[data-role="operation-filter"]');
                    var status = container.querySelector('[data-role="filter-status"]');
                    var noResultsEl = container.querySelector('[data-role="no-results"]');

                    var searchTerm = searchInput ? normaliseSearch(searchInput.value) : '';
                    var selectedOperation = operationSelect ? operationSelect.value : '';
                    var filtersActive = searchTerm !== '' || selectedOperation !== '';

                    var items = container.querySelectorAll('[data-role="summary-item"]');
                    var total = items.length;
                    var visible = 0;

                    items.forEach(function(item) {
                        var matches = true;
                        var searchIndex = item.getAttribute('data-search-index') || '';
                        var itemOperation = item.getAttribute('data-operation') || '';

                        if (searchTerm !== '') {
                            matches = searchIndex.indexOf(searchTerm) !== -1;
                        }

                        if (matches && selectedOperation !== '') {
                            matches = itemOperation === selectedOperation;
                        }

                        if (matches) {
                            item.removeAttribute('hidden');
                            visible += 1;
                        } else {
                            item.setAttribute('hidden', 'hidden');
                        }
                    });

                    if (noResultsEl) {
                        if (visible === 0 && total > 0) {
                            noResultsEl.removeAttribute('hidden');
                        } else {
                            noResultsEl.setAttribute('hidden', 'hidden');
                        }
                    }

                    if (status) {
                        if (!filtersActive) {
                            status.setAttribute('hidden', 'hidden');
                        } else {
                            var message;
                            if (visible === 0) {
                                message = status.getAttribute('data-empty-message') || 'No matches found';
                            } else if (searchTerm !== '' && selectedOperation !== '') {
                                message = 'Showing ' + visible + ' of ' + total + ' summaries';
                            } else if (searchTerm !== '') {
                                var template = status.getAttribute('data-match-template') || '';
                                message = template.replace('%1$d', visible).replace('%2$d', total).replace('%3$s', searchTerm);
                            } else {
                                message = 'Showing ' + visible + ' of ' + total + ' summaries';
                            }

                            status.textContent = message;
                            status.className = 'badge badge-phoenix badge-phoenix-primary text-uppercase fs-9 mb-3';
                            status.removeAttribute('hidden');
                        }
                    }
                }

                function initFilters(container) {
                    if (!container || container.dataset.filtersInitialised === '1') {
                        return;
                    }

                    container.dataset.filtersInitialised = '1';

                    var searchInput = container.querySelector('[data-role="ai-search"]');
                    var operationSelect = container.querySelector('[data-role="operation-filter"]');
                    var form = container.querySelector('[data-role="ai-filter-form"]');

                    var handler = function() {
                        applyFilters(container);
                    };

                    if (form) {
                        form.addEventListener('submit', function(event) {
                            event.preventDefault();
                            handler();
                        });
                    }

                    if (searchInput) {
                        searchInput.addEventListener('input', handler);
                    }

                    if (operationSelect) {
                        operationSelect.addEventListener('change', handler);
                    }

                    handler();
                }

                function ready(callback) {
                    if (document.readyState === 'loading') {
                        document.addEventListener('DOMContentLoaded', function onReady() {
                            document.removeEventListener('DOMContentLoaded', onReady);
                            callback();
                        });
                    } else {
                        callback();
                    }
                }

                ready(function() {
                    document.querySelectorAll('.wecoza-ai-summary-wrapper').forEach(initFilters);
                });
            })();
        </script>
        <?php

        return trim((string) ob_get_clean());
    }
}
