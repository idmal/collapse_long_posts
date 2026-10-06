<?php

if (!defined('FLATBB')) {
    exit;
}

function collapse_long_posts_js($value, array $ctx): string
{
    if (!plugin_setting('collapse_long_posts', 'enabled', 1)) {
        return $value;
    }

    $threshold = (int) plugin_setting(
        'collapse_long_posts',
        'threshold',
        800
    );

    $threshold = max(200, min(3000, $threshold));

    /*
     * These keys are translated through lang/fa.php.
     * Do not put Persian text directly here.
     */
    $labelExpand = t('CollapseLongPosts: Expand');
    $labelCollapse = t('CollapseLongPosts: Collapse');

    /*
     * json_encode safely escapes Persian text and quotes
     * before placing it inside JavaScript.
     */
    $jsThreshold = (string) $threshold;
    $jsLabelExpand = json_encode(
        $labelExpand,
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    );
    $jsLabelCollapse = json_encode(
        $labelCollapse,
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    );

    $js = <<<JS
(function () {
    'use strict';

    var THRESHOLD = {$jsThreshold};
    var LABEL_EXPAND = {$jsLabelExpand};
    var LABEL_COLLAPSE = {$jsLabelCollapse};

    var floatBtn = document.createElement('button');
    floatBtn.type = 'button';
    floatBtn.className = 'clp-toggle clp-floating';
    floatBtn.textContent = LABEL_COLLAPSE;
    floatBtn.style.display = 'none';
    document.body.appendChild(floatBtn);

    var floatOwner = null;

    floatBtn.addEventListener('click', function () {
        if (floatOwner && floatOwner.btn) {
            floatOwner.btn.click();
        }
    });

    function clp_dedupe() {
        document.querySelectorAll('.post-content').forEach(function (content) {
            var next = content.nextElementSibling;
            var seen = 0;

            while (
                next &&
                next.classList &&
                next.classList.contains('clp-wrap')
            ) {
                seen++;

                if (seen > 1) {
                    next.parentNode.removeChild(next);
                    break;
                }

                next = next.nextElementSibling;
            }
        });
    }

    function clp_updateFloating() {
        var candidates = [];

        document
            .querySelectorAll('.post-content.clp-expanded')
            .forEach(function (content) {
                var wrap = content.nextElementSibling;

                if (
                    !wrap ||
                    !wrap.classList.contains('clp-wrap')
                ) {
                    return;
                }

                var btn = wrap.querySelector('.clp-toggle');

                if (!btn) {
                    return;
                }

                var rect = content.getBoundingClientRect();
                var btnRect = btn.getBoundingClientRect();

                if (
                    rect.bottom <= 0 ||
                    rect.top >= window.innerHeight
                ) {
                    return;
                }

                if (
                    rect.bottom > window.innerHeight &&
                    btnRect.top > window.innerHeight
                ) {
                    candidates.push({
                        btn: btn,
                        top: rect.top
                    });
                }
            });

        if (candidates.length === 0) {
            if (floatBtn.style.display !== 'none') {
                floatBtn.style.display = 'none';
            }

            floatOwner = null;
            return;
        }

        candidates.sort(function (a, b) {
            return b.top - a.top;
        });

        floatOwner = {
            btn: candidates[0].btn
        };

        if (floatBtn.style.display === 'none') {
            floatBtn.style.display = '';
        }
    }

    function clp_collapseSmart(content, doCollapse) {
        var visibleExpanded = [];

        document
            .querySelectorAll('.post-content.clp-expanded')
            .forEach(function (candidate) {
                var rect = candidate.getBoundingClientRect();

                if (
                    rect.bottom > 0 &&
                    rect.top < window.innerHeight
                ) {
                    visibleExpanded.push(candidate);
                }
            });

        var others = visibleExpanded.filter(function (candidate) {
            return candidate !== content;
        });

        if (others.length === 0) {
            doCollapse();

            var rect = content.getBoundingClientRect();
            var absTop = rect.top + window.scrollY;
            var height = rect.height;
            var viewportHeight = window.innerHeight;
            var targetY;

            if (height <= viewportHeight - 40) {
                targetY = absTop - (viewportHeight - height) / 2;
            } else {
                targetY = absTop - 20;
            }

            window.scrollTo(0, Math.max(0, targetY));
            return;
        }

        var myRect = content.getBoundingClientRect();
        var anchor = others[0];
        var minDistance = Infinity;

        others.forEach(function (candidate) {
            var rect = candidate.getBoundingClientRect();
            var distance = Math.abs(rect.top - myRect.top);

            if (distance < minDistance) {
                minDistance = distance;
                anchor = candidate;
            }
        });

        var anchorTopBefore = anchor.getBoundingClientRect().top;

        doCollapse();

        var anchorTopAfter = anchor.getBoundingClientRect().top;
        var deltaY = anchorTopAfter - anchorTopBefore;

        if (Math.abs(deltaY) > 1) {
            window.scrollBy(0, deltaY);
        }
    }

    var rafPending = false;

    function onScrollOrResize() {
        if (rafPending) {
            return;
        }

        rafPending = true;

        requestAnimationFrame(function () {
            rafPending = false;
            clp_updateFloating();
        });
    }

    function clp_setupPost(post) {
        var content = post.querySelector('.post-content');

        if (
            !content ||
            content.dataset.clpReady === '1'
        ) {
            return;
        }

        content.dataset.clpReady = '1';

        function clp_check() {
            if (content.scrollHeight <= THRESHOLD) {
                return;
            }

            var next = content.nextElementSibling;

            if (
                next &&
                next.classList.contains('clp-wrap')
            ) {
                return;
            }

            var wrap = document.createElement('div');
            wrap.className = 'clp-wrap';

            var btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'clp-toggle';
            btn.textContent = LABEL_EXPAND;

            wrap.appendChild(btn);
            content.parentNode.insertBefore(
                wrap,
                content.nextSibling
            );

            content.style.maxHeight = THRESHOLD + 'px';
            content.classList.add('clp-collapsed');

            function collapse() {
                content.classList.add('clp-collapsed');
                content.classList.remove('clp-expanded');
                content.style.maxHeight = THRESHOLD + 'px';
                btn.textContent = LABEL_EXPAND;

                if (
                    floatOwner &&
                    floatOwner.btn === btn
                ) {
                    floatBtn.style.display = 'none';
                    floatOwner = null;
                }
            }

            function expand() {
                content.classList.remove('clp-collapsed');
                content.classList.add('clp-expanded');
                content.style.maxHeight = 'none';
                btn.textContent = LABEL_COLLAPSE;
                clp_updateFloating();
            }

            btn.addEventListener('click', function (event) {
                event.stopPropagation();

                if (
                    content.classList.contains('clp-collapsed')
                ) {
                    expand();
                } else {
                    clp_collapseSmart(content, collapse);
                }
            });

            content.addEventListener('click', function (event) {
                if (
                    content.classList.contains('clp-collapsed')
                ) {
                    return;
                }

                if (
                    event.target.closest(
                        'a, img, code, pre, button, input, textarea'
                    )
                ) {
                    return;
                }

                clp_collapseSmart(content, collapse);
            });
        }

        var images = content.querySelectorAll('img');
        var pending = 0;

        images.forEach(function (image) {
            if (!image.complete) {
                pending++;

                image.addEventListener('load', function () {
                    if (--pending <= 0) {
                        clp_check();
                    }
                });

                image.addEventListener('error', function () {
                    if (--pending <= 0) {
                        clp_check();
                    }
                });
            }
        });

        if (pending === 0) {
            setTimeout(clp_check, 150);
        } else {
            setTimeout(clp_check, 3000);
        }
    }

    function clp_scan() {
        clp_dedupe();

        document
            .querySelectorAll('[data-post-id]')
            .forEach(clp_setupPost);
    }

    window.addEventListener(
        'scroll',
        onScrollOrResize,
        { passive: true }
    );

    window.addEventListener(
        'resize',
        onScrollOrResize,
        { passive: true }
    );

    if (window.MutationObserver) {
        new MutationObserver(function () {
            clp_scan();
            clp_updateFloating();
        }).observe(document.body, {
            childList: true,
            subtree: true
        });
    }

    clp_scan();
    setInterval(clp_updateFloating, 1000);
})();
JS;

$css = '
.clp-wrap {
    display: flex;
    justify-content: center;
    align-items: center;
    width: 100%;
    margin-top: 10px;
    text-align: center;
    direction: rtl;
}

.clp-toggle {
    display: inline-block;
    padding: 4px 14px;
    border: 1px solid var(--line, #ddd);
    border-radius: var(--radius-sm, 4px);
    color: var(--brand, #e7672e);
    background: transparent;
    cursor: pointer;
    font-family: inherit;
    font-size: 13px;
    line-height: 1.5;
    text-align: center;
}

.clp-toggle:hover {
    border-color: var(--brand, #e7672e);
    color: var(--brand, #e7672e);
}

.clp-toggle.clp-floating {
    position: static;
    display: inline-block;
    margin: 10px auto 0;
    padding: 4px 14px;
    border: 1px solid var(--line, #ddd);
    border-radius: var(--radius-sm, 4px);
    color: var(--brand, #e7672e);
    background: transparent;
    box-shadow: none;
    transform: none;
    font-size: 13px;
}

.clp-toggle.clp-floating:hover {
    border-color: var(--brand, #e7672e);
    color: var(--brand, #e7672e);
    background: transparent;
    transform: none;
}

.post-content.clp-collapsed {
    position: relative;
    overflow: hidden;
    transition: max-height .25s ease;
}

.post-content.clp-collapsed::after {
    content: "";
    position: absolute;
    right: 0;
    bottom: 0;
    left: 0;
    height: 60px;
    pointer-events: none;
    background: linear-gradient(
        transparent,
        var(--panel, #fff)
    );
}

@media (prefers-color-scheme: dark) {
    .post-content.clp-collapsed::after {
        background: linear-gradient(
            transparent,
            var(--panel, #1b202b)
        );
    }
}
';

    return $value
        . '<style>' . $css . '</style>'
        . script_tag($js);
}

return [
    'id' => 'collapse_long_posts',
    'name' => 'Collapse Long Posts',
    'version' => '2.8.1',
    'description' =>
        'Automatically collapse long posts to keep your forum clean — users can expand or collapse anytime.',
    'author' => 'GatekeeperMaxim',
    'requires' => [
        'flatbb' => '0.1.0',
    ],
    'hooks' => [
        'region.footer.right' => 'collapse_long_posts_js',
    ],
    'settings' => [
        'enabled' => [
            'type' => 'checkbox',
            'label' => 'Enable auto-collapse',
            'default' => 1,
        ],
        'threshold' => [
            'type' => 'number',
            'label' => 'Collapse threshold (px)',
            'default' => 800,
            'min' => 200,
            'max' => 3000,
        ],
    ],
];