<?php
declare(strict_types=1);

namespace WeCozaEvents\Shortcodes;

use RuntimeException;
use WeCozaEvents\Services\ClassTaskService;
use WeCozaEvents\Support\WordPressRequest;
use WeCozaEvents\Views\Presenters\ClassTaskPresenter;
use WeCozaEvents\Views\TemplateRenderer;

use function absint;
use function add_shortcode;
use function admin_url;
use function esc_html;
use function esc_html__;
use function esc_js;
use function preg_replace;
use function remove_query_arg;
use function shortcode_atts;
use function sprintf;
use function str_replace;
use function strtolower;
use function trim;
use function uniqid;
use function wp_create_nonce;

final class EventTasksShortcode
{
    private const DEFAULT_LIMIT = 20;

    private ClassTaskService $service;
    private ClassTaskPresenter $presenter;
    private TemplateRenderer $renderer;
    private WordPressRequest $request;
    private bool $assetsPrinted = false;

    public function __construct(
        ?ClassTaskService $service = null,
        ?ClassTaskPresenter $presenter = null,
        ?TemplateRenderer $renderer = null,
        ?WordPressRequest $request = null
    ) {
        $this->service = $service ?? new ClassTaskService();
        $this->presenter = $presenter ?? new ClassTaskPresenter();
        $this->renderer = $renderer ?? new TemplateRenderer();
        $this->request = $request ?? new WordPressRequest();
    }

    public static function register(?self $shortcode = null): void
    {
        $instance = $shortcode ?? new self();
        add_shortcode('wecoza_event_tasks', [$instance, 'render']);
    }

    public function render(array $atts = [], string $content = '', string $tag = ''): string
    {
        $atts = shortcode_atts([
            'limit' => self::DEFAULT_LIMIT,
        ], $atts, $tag);

        $limit = absint($atts['limit']);
        if ($limit <= 0) {
            $limit = self::DEFAULT_LIMIT;
        }

        $sortParam = $this->request->getQueryString('wecoza_tasks_sort', '');
        $sortDirection = $sortParam === 'start_asc' ? 'asc' : 'desc';
        $classIdFilter = $this->request->getQueryInt('class_id');
        $prioritiseOpen = $sortParam === '' && $classIdFilter === null;

        try {
            $items = $this->service->getClassTasks($limit, $sortDirection, $prioritiseOpen, $classIdFilter);
        } catch (RuntimeException $exception) {
            return $this->wrapMessage(
                sprintf(
                    esc_html__('Unable to load tasks: %s', 'wecoza-events'),
                    esc_html($exception->getMessage())
                )
            );
        }

        if ($items === []) {
            if ($classIdFilter !== null) {
                return $this->wrapMessage(
                    sprintf(
                        esc_html__('No tasks are available for class #%d.', 'wecoza-events'),
                        $classIdFilter
                    )
                );
            }

            return $this->wrapMessage(esc_html__('No classes available.', 'wecoza-events'));
        }

        $classes = $this->presenter->present($items);
        $classSpecific = $classIdFilter !== null;

        $instanceId = uniqid('wecoza-tasks-');
        $sortIconClass = $sortDirection === 'asc' ? 'bi-sort-up' : 'bi-sort-down';

        return $this->renderer->render('event-tasks/main', [
            'assets' => $this->getAssets(),
            'classes' => $classes,
            'classSpecific' => $classSpecific,
            'classIdFilter' => $classIdFilter,
            'openTaskOptions' => $classSpecific ? [] : $this->buildOpenTaskOptions($classes),
            'instanceId' => $instanceId,
            'searchInputId' => $instanceId . '-search',
            'openTaskSelectId' => $instanceId . '-open-task',
            'viewAllUrl' => $classSpecific ? remove_query_arg('class_id') : '',
            'nonce' => wp_create_nonce('wecoza_events_tasks'),
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'sortDirection' => $sortDirection,
            'sortIconClass' => $sortIconClass,
        ]);
    }

    /**
     * @param array<int, array<string, mixed>> $classes
     * @return array<int, array{label:string,value:string}>
     */
    private function buildOpenTaskOptions(array $classes): array
    {
        $labels = $this->presenter->collectOpenTaskLabels($classes);

        $options = [];
        foreach ($labels as $label) {
            $options[] = [
                'label' => $label,
                'value' => $this->normaliseForToken($label),
            ];
        }

        return $options;
    }

    private function normaliseForToken(string $value): string
    {
        $value = str_replace('|', ' ', strtolower(trim($value)));
        $value = preg_replace('/\s+/', ' ', $value);

        return $value === null ? '' : $value;
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
                function escapeHtml(value) {
                    return String(value ?? '').replace(/[&<>"']/g, function(match) {
                        switch (match) {
                            case '&': return '&amp;';
                            case '<': return '&lt;';
                            case '>': return '&gt;';
                            case '"': return '&quot;';
                            case "'": return '&#039;';
                        }
                        return match;
                    });
                }

                function normaliseToken(value) {
                    return String(value || '')
                        .toLowerCase()
                        .replace(/\|/g, ' ')
                        .replace(/\s+/g, ' ')
                        .trim();
                }

                function normaliseSearch(value) {
                    return String(value || '')
                        .toLowerCase()
                        .replace(/\s+/g, ' ')
                        .trim();
                }

                function applyTaskFilters(container) {
                    if (!container) {
                        return;
                    }

                    var searchInput = container.querySelector('[data-role="tasks-search"]');
                    var select = container.querySelector('[data-role="open-task-filter"]');
                    var status = container.querySelector('[data-role="filter-status"]');
                    var noResultsRow = container.querySelector('[data-role="no-results"]');

                    var searchTerm = searchInput ? normaliseSearch(searchInput.value) : '';
                    var selectedTask = select ? normaliseToken(select.value) : '';
                    var filtersActive = searchTerm !== '' || selectedTask !== '';

                    var rows = container.querySelectorAll('tr[data-role="class-row"]');
                    var total = rows.length;
                    var visible = 0;

                    rows.forEach(function(row) {
                        var matches = true;
                        var searchIndex = row.getAttribute('data-search-index') || '';

                        if (searchTerm !== '') {
                            matches = searchIndex.indexOf(searchTerm) !== -1;
                        }

                        if (matches && selectedTask !== '') {
                            var tokens = row.getAttribute('data-open-tasks') || '';
                            tokens = tokens ? tokens.split('|') : [];
                            matches = tokens.indexOf(selectedTask) !== -1;
                        }

                        if (matches) {
                            row.removeAttribute('hidden');
                            visible += 1;
                        } else {
                            row.setAttribute('hidden', 'hidden');
                        }

                        var panelId = row.getAttribute('data-panel-id');
                        if (!matches && panelId) {
                            var panelRow = container.querySelector('.wecoza-task-panel-row[data-panel-id="' + panelId + '"]');
                            if (panelRow) {
                                panelRow.setAttribute('hidden', 'hidden');
                            }
                        }
                    });

                    if (noResultsRow) {
                        if (visible === 0 && total > 0) {
                            noResultsRow.removeAttribute('hidden');
                        } else {
                            noResultsRow.setAttribute('hidden', 'hidden');
                        }
                    }

                    if (status) {
                        if (!filtersActive) {
                            status.setAttribute('hidden', 'hidden');
                            status.textContent = '';
                            status.className = '';
                        } else {
                            var searchActive = searchTerm !== '';
                            var message;

                            if (visible === 0) {
                                message = status.getAttribute('data-empty-message') || '';
                            } else if (searchActive && selectedTask !== '') {
                                var matchFilterTemplate = status.getAttribute('data-match-filter-template') || '';
                                message = matchFilterTemplate ? matchFilterTemplate.replace('%1$d', visible).replace('%2$d', total).replace('%3$s', searchTerm) : visible + ' / ' + total;
                            } else if (searchActive) {
                                var matchTemplate = status.getAttribute('data-match-template') || '';
                                message = matchTemplate ? matchTemplate.replace('%1$d', visible).replace('%2$d', total).replace('%3$s', searchTerm) : visible + ' / ' + total;
                            } else {
                                var filterTemplate = status.getAttribute('data-filter-template') || '';
                                message = filterTemplate ? filterTemplate.replace('%1$d', visible).replace('%2$d', total) : visible + ' / ' + total;
                            }

                            status.textContent = message;
                            status.className = 'badge badge-phoenix badge-phoenix-primary text-uppercase fs-9 mb-2';
                            status.removeAttribute('hidden');
                        }
                    }
                }

                function initTaskFilters(container) {
                    if (!container || container.dataset.filtersInitialised === '1') {
                        return;
                    }

                    if (container.dataset.classFilter) {
                        container.dataset.filtersInitialised = '1';
                        return;
                    }

                    container.dataset.filtersInitialised = '1';

                    var searchInput = container.querySelector('[data-role="tasks-search"]');
                    var select = container.querySelector('[data-role="open-task-filter"]');
                    var form = container.querySelector('[data-role="tasks-filter-form"]');

                    var handler = function() {
                        applyTaskFilters(container);
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

                    if (select) {
                        select.addEventListener('change', handler);
                    }

                    handler();
                }

                function updateRowFilterMetadata(container, panelRow, openTasks) {
                    if (!container || !panelRow) {
                        return;
                    }

                    var panelId = panelRow.getAttribute('data-panel-id');
                    if (!panelId) {
                        return;
                    }

                    var summaryRow = container.querySelector('tr[data-role="class-row"][data-panel-id="' + panelId + '"]');
                    if (!summaryRow) {
                        return;
                    }

                    var tokens = Array.isArray(openTasks) ? openTasks.map(function(task) {
                        if (!task || typeof task !== 'object') {
                            return '';
                        }
                        return normaliseToken(task.label);
                    }).filter(function(token) {
                        return token !== '';
                    }) : [];

                    summaryRow.setAttribute('data-open-tasks', tokens.join('|'));

                    var base = summaryRow.getAttribute('data-search-base') || '';
                    var searchIndex = base;

                    var statusValue = summaryRow.getAttribute('data-status-label') || '';
                    if (statusValue) {
                        searchIndex = searchIndex ? searchIndex + ' ' + statusValue : statusValue;
                    }

                    tokens.forEach(function(token) {
                        if (token && searchIndex.indexOf(token) === -1) {
                            searchIndex = searchIndex ? searchIndex + ' ' + token : token;
                        }
                    });

                    summaryRow.setAttribute('data-search-index', searchIndex);
                }

                function requiresNoteValue(input) {
                    return !!input && input.dataset.noteRequired === '1';
                }

                function showNoteValidationError(input) {
                    if (!input) {
                        return;
                    }

                    input.classList.add('is-invalid');
                    input.setAttribute('aria-invalid', 'true');
                }

                function clearNoteValidationError(input) {
                    if (!input) {
                        return;
                    }

                    input.classList.remove('is-invalid');
                    input.removeAttribute('aria-invalid');
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

                function buildOpenTaskHtml(task, classId, disabled) {
                    var noteId = 'wecoza-note-' + classId + '-' + task.id;
                    var noteRequiredAttr = task.note_required ? ' data-note-required="1"' : ' data-note-required="0"';
                    var ariaRequiredAttr = task.note_required ? ' aria-required="true"' : '';
                    var requiredFeedback = task.note_required_message
                        ? '<div class="invalid-feedback small">' + escapeHtml(task.note_required_message) + '</div>'
                        : '';
                    return '' +
                        '<li class="list-group-item d-flex flex-row align-items-center justify-content-between gap-2 m-1" data-task-id="' + escapeHtml(task.id) + '">' +
                            '<div class="fw-semibold text-body w-30">' + escapeHtml(task.label) + '</div>' +
                            '<div class="d-flex flex-row gap-2 align-items-start flex-grow-1">' +
                                '<div class="flex-grow-1">' +
                                    '<label class="visually-hidden" for="' + escapeHtml(noteId) + '">' + escapeHtml(task.note_label) + '</label>' +
                                    '<input id="' + escapeHtml(noteId) + '" class="form-control form-control-sm wecoza-task-note" type="text" placeholder="' + escapeHtml(task.note_placeholder) + '"' + noteRequiredAttr + ariaRequiredAttr + ' ' + (disabled ? 'disabled' : '') + '>' +
                                    requiredFeedback +
                                '</div>' +
                                '<div class="d-flex">' +
                                    '<button type="button" class="btn btn-subtle-success btn-sm wecoza-task-action" data-action="complete" ' + (disabled ? 'disabled' : '') + '>' + escapeHtml(task.complete_label) + '</button>' +
                                '</div>' +
                            '</div>' +
                        '</li>';
                }

                function buildCompletedTaskHtml(task, disabled) {
                    return '' +
                        '<li class="list-group-item" data-task-id="' + escapeHtml(task.id) + '">' +
                            '<div class="d-flex flex-wrap align-items-center justify-content-between gap-2">' +
                                '<span class="fw-semibold text-body">' + escapeHtml(task.label) + '</span>' +
                                '<button type="button" class="btn btn-subtle-primary btn-sm wecoza-task-action" data-action="reopen" ' + (disabled ? 'disabled' : '') + '>' + escapeHtml(task.reopen_label) + '</button>' +
                            '</div>' +
                            '<div class="text-body-secondary small mt-2">' + escapeHtml(task.completed_by + ' • ' + task.completed_at) + '</div>' +
                            (task.note ? '<div class="border rounded-2 bg-body-tertiary text-body-secondary mt-2 px-2 py-1">' + escapeHtml(task.note) + '</div>' : '') +
                        '</li>';
                }

                function updateEmptyState(panel, type, isEmpty) {
                    var indicator = panel.querySelector('[data-empty="' + type + '"]');
                    if (!indicator) {
                        return;
                    }

                    if (isEmpty) {
                        indicator.removeAttribute('hidden');
                    } else {
                        indicator.setAttribute('hidden', 'hidden');
                    }
                }

                function updateSummaryStatus(container, panelRow, openTasks) {
                    if (!container || !panelRow) {
                        return;
                    }

                    var panelId = panelRow.getAttribute('data-panel-id');
                    if (!panelId) {
                        return;
                    }

                    var summaryRow = container.querySelector('tr[data-role="class-row"][data-panel-id="' + panelId + '"]');
                    if (!summaryRow) {
                        return;
                    }

                    var badge = summaryRow.querySelector('[data-role="status-badge"]');
                    if (!badge) {
                        return;
                    }

                    var openCount = Array.isArray(openTasks) ? openTasks.length : 0;
                    var openTemplate = container.getAttribute('data-open-label-template') || 'Open +%d';
                    var completeLabel = container.getAttribute('data-complete-label') || 'Completed';
                    var openClass = container.getAttribute('data-open-badge-class') || 'badge-phoenix-warning';
                    var completeClass = container.getAttribute('data-complete-badge-class') || 'badge-phoenix-secondary';

                    var label;
                    var variant;
                    if (openCount > 0) {
                        if (openTemplate.indexOf('%d') !== -1) {
                            label = openTemplate.replace('%d', openCount);
                        } else {
                            label = openTemplate + ' ' + openCount;
                        }
                        variant = openClass;
                    } else {
                        label = completeLabel.toUpperCase();
                        variant = completeClass;
                    }

                    badge.textContent = label;

                    var classes = badge.className.split(' ').filter(function(name) {
                        return name && !/^badge-phoenix-/.test(name);
                    });
                    classes.push(variant);
                    badge.className = classes.join(' ');

                    summaryRow.setAttribute('data-status-label', normaliseToken(label));
                }

                ready(function() {
                    document.querySelectorAll('.wecoza-event-tasks').forEach(initTaskFilters);
                });

                document.addEventListener('click', function(event) {
                    var sortToggle = event.target.closest('[data-role="sort-toggle"]');
                    if (sortToggle) {
                        event.preventDefault();

                        var wrapper = sortToggle.closest('.wecoza-event-tasks');
                        if (!wrapper) {
                            return;
                        }

                        if (wrapper.getAttribute('data-class-filter')) {
                            return;
                        }

                        var currentSort = (wrapper.getAttribute('data-sort-direction') || 'desc').toLowerCase();
                        var nextSort = currentSort === 'asc' ? 'desc' : 'asc';

                        var url = new URL(window.location.href);
                        url.searchParams.set('wecoza_tasks_sort', nextSort === 'asc' ? 'start_asc' : 'start_desc');
                        url.searchParams.delete('paged');
                        url.searchParams.delete('page');

                        window.location.href = url.toString();
                        return;
                    }

                    var toggle = event.target.closest('.wecoza-task-toggle');
                    if (toggle) {
                        event.preventDefault();

                        var container = toggle.closest('.wecoza-event-tasks');
                        if (!container) {
                            return;
                        }

                        var targetId = toggle.getAttribute('data-target');
                        if (!targetId) {
                            return;
                        }

                        var panel = container.querySelector('.wecoza-task-panel-row[data-panel-id="' + targetId + '"]');
                        if (!panel) {
                            return;
                        }

                        var shouldOpen = panel.hasAttribute('hidden');

                        container.querySelectorAll('.wecoza-task-panel-row').forEach(function(row) {
                            row.setAttribute('hidden', 'hidden');
                        });

                        container.querySelectorAll('.wecoza-task-toggle').forEach(function(btn) {
                            btn.setAttribute('aria-expanded', 'false');
                        });

                        if (shouldOpen) {
                            panel.removeAttribute('hidden');
                            toggle.setAttribute('aria-expanded', 'true');
                        }

                        return;
                    }

                    var button = event.target.closest('.wecoza-task-action');
                    if (!button) {
                        return;
                    }

                    event.preventDefault();

                    var panelRow = button.closest('.wecoza-task-panel-row');
                    var panel = panelRow ? panelRow.querySelector('.wecoza-task-panel-content') : null;
                    var wrapper = button.closest('.wecoza-event-tasks');
                    if (!panel || !wrapper) {
                        return;
                    }

                    if (panel.dataset.manageable !== '1') {
                        window.alert('<?php echo esc_js(__('Tasks cannot be updated for this class yet.', 'wecoza-events')); ?>');
                        return;
                    }

                    var taskItem = button.closest('[data-task-id]');
                    if (!taskItem) {
                        return;
                    }

                    var noteField = taskItem.querySelector('.wecoza-task-note');

                    var formData = new FormData();
                    formData.append('action', 'wecoza_events_task_update');
                    formData.append('nonce', wrapper.dataset.nonce);
                    formData.append('log_id', panel.dataset.logId || '');
                    formData.append('task_id', taskItem.dataset.taskId || '');
                    formData.append('task_action', button.dataset.action || '');

                    if (button.dataset.action === 'complete') {
                        var requiresNote = requiresNoteValue(noteField);
                        var noteValue = noteField ? noteField.value.trim() : '';

                        if (requiresNote && noteValue === '') {
                            showNoteValidationError(noteField);
                            if (noteField) {
                                noteField.focus();
                            }
                            return;
                        }

                        if (noteField) {
                            clearNoteValidationError(noteField);
                        }

                        formData.append('note', noteField ? noteField.value : '');
                    }

                    button.disabled = true;

                    fetch(wrapper.dataset.ajaxUrl, {
                        method: 'POST',
                        credentials: 'same-origin',
                        body: formData
                    }).then(function(response) {
                        if (!response.ok) {
                            throw new Error('Network error');
                        }
                        return response.json();
                    }).then(function(payload) {
                        if (!payload || !payload.success) {
                            throw new Error(payload && payload.data && payload.data.message ? payload.data.message : 'Request failed');
                        }

                        var data = payload.data;
                        var disabled = panel.dataset.manageable !== '1';
                        var openList = panel.querySelector('[data-role="open-list"]');
                        var completedList = panel.querySelector('[data-role="completed-list"]');
                        if (openList) {
                            openList.innerHTML = data.tasks.open.map(function(task) { return buildOpenTaskHtml(task, panel.dataset.classId, disabled); }).join('');
                            updateEmptyState(panel, 'open', data.tasks.open.length === 0);
                        }
                        if (completedList) {
                            completedList.innerHTML = data.tasks.completed.map(function(task) { return buildCompletedTaskHtml(task, disabled); }).join('');
                            updateEmptyState(panel, 'completed', data.tasks.completed.length === 0);
                        }

                        updateSummaryStatus(wrapper, panelRow, data.tasks.open || []);
                        updateRowFilterMetadata(wrapper, panelRow, data.tasks.open || []);
                        applyTaskFilters(wrapper);
                    }).catch(function(error) {
                        window.alert(error.message || '<?php echo esc_js(__('Unable to update task.', 'wecoza-events')); ?>');
                    }).finally(function() {
                        button.disabled = false;
                    });
                });

                document.addEventListener('input', function(event) {
                    var input = event.target;
                    if (!input || !input.classList || !input.classList.contains('wecoza-task-note')) {
                        return;
                    }

                    if (!requiresNoteValue(input)) {
                        clearNoteValidationError(input);
                        return;
                    }

                    if (input.value.trim() !== '') {
                        clearNoteValidationError(input);
                    }
                });
            })();
        </script>
        <?php

        return trim((string) ob_get_clean());
    }
}
