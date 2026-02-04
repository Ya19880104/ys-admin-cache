/**
 * YS Admin Cache 設定頁面腳本
 *
 * @package YS_Admin_Cache
 */

/* global jQuery, ys_admin_cache_params */
(function ($) {
    'use strict';

    /**
     * 設定頁面功能
     */
    const YSAdminCacheSettings = {
        /**
         * 統計刷新間隔（秒）
         */
        statsRefreshInterval: 10,

        /**
         * 倒數計時器
         */
        countdownTimer: null,

        /**
         * 刷新計時器
         */
        refreshTimer: null,

        /**
         * 當前倒數
         */
        countdown: 10,

        /**
         * 初始化
         */
        init: function () {
            this.bindEvents();
            this.startStatsRefresh();
        },

        /**
         * 綁定事件
         */
        bindEvents: function () {
            // 清除快取按鈕
            $('#ys-clear-all-cache').on('click', this.clearCache.bind(this));

            // 全選 / 取消全選
            $('#ys-select-all-pages').on('change', this.toggleSelectAll.bind(this));

            // 更新全選狀態
            $('.ys-page-item').on('change', this.updateSelectAllState.bind(this));

            // 時間預設按鈕
            $('.ys-duration-preset').on('click', this.setDurationPreset.bind(this));

            // 初始化全選狀態
            this.updateSelectAllState();
        },

        /**
         * 開始統計自動刷新
         */
        startStatsRefresh: function () {
            const self = this;

            // 如果沒有統計區塊則不啟動
            if ($('#ys-cache-stats-container').length === 0) {
                return;
            }

            // 初始化倒數
            this.countdown = this.statsRefreshInterval;
            this.updateCountdown();

            // 倒數計時器（每秒更新）
            this.countdownTimer = setInterval(function () {
                self.countdown--;
                self.updateCountdown();

                if (self.countdown <= 0) {
                    self.countdown = self.statsRefreshInterval;
                }
            }, 1000);

            // 刷新計時器
            this.refreshTimer = setInterval(function () {
                self.refreshStats();
            }, this.statsRefreshInterval * 1000);
        },

        /**
         * 更新倒數顯示
         */
        updateCountdown: function () {
            $('#ys-stats-countdown').text('(' + this.countdown + ')');
        },

        /**
         * 刷新統計
         */
        refreshStats: function () {
            const self = this;

            $.ajax({
                url: ys_admin_cache_params.ajax_url,
                method: 'POST',
                data: {
                    action: 'ys_admin_cache_get_stats',
                    nonce: ys_admin_cache_params.nonce
                },
                success: function (response) {
                    if (response.success && response.data) {
                        // 更新統計數值（帶動畫效果）
                        self.animateValue('#ys-stat-count', response.data.count);
                        self.animateValue('#ys-stat-size', response.data.size);
                        $('#ys-stat-path').text(response.data.path);

                        // 更新 Cron 狀態
                        if (response.data.cron_next_run !== undefined) {
                            self.animateValue('#ys-cron-next-run', response.data.cron_next_run);
                        }
                    }
                }
            });
        },

        /**
         * 數值更新動畫
         *
         * @param {string} selector 選擇器
         * @param {string} newValue 新值
         */
        animateValue: function (selector, newValue) {
            const $el = $(selector);
            const oldValue = $el.text();

            if (oldValue !== newValue) {
                $el.fadeOut(150, function () {
                    $(this).text(newValue).fadeIn(150);
                });
            }
        },

        /**
         * 設定時間預設值
         *
         * @param {Event} e 事件物件
         */
        setDurationPreset: function (e) {
            e.preventDefault();
            const value = $(e.target).data('value');
            $('#ys_admin_cache_duration').val(value).trigger('change');
        },

        /**
         * 清除快取
         *
         * @param {Event} e 事件物件
         */
        clearCache: function (e) {
            e.preventDefault();

            const $button = $(e.target);
            const $status = $('#ys-clear-cache-status');
            const labels = ys_admin_cache_params.labels;
            const self = this;

            // 確認
            if (!window.confirm(labels.confirm_clear)) {
                return;
            }

            // 禁用按鈕
            $button.prop('disabled', true);
            $status.removeClass('success error').addClass('loading').text(labels.clearing);

            // 發送請求
            $.ajax({
                url: ys_admin_cache_params.ajax_url,
                method: 'POST',
                data: {
                    action: 'ys_admin_cache_clear',
                    nonce: ys_admin_cache_params.nonce
                },
                success: function (response) {
                    if (response.success) {
                        $status.removeClass('loading error').addClass('success').text(labels.cleared);

                        // 立即刷新統計
                        self.refreshStats();
                    } else {
                        $status.removeClass('loading success').addClass('error')
                            .text(response.data?.message || labels.error);
                    }
                },
                error: function () {
                    $status.removeClass('loading success').addClass('error').text(labels.error);
                },
                complete: function () {
                    $button.prop('disabled', false);
                }
            });
        },

        /**
         * 全選 / 取消全選
         *
         * @param {Event} e 事件物件
         */
        toggleSelectAll: function (e) {
            const isChecked = $(e.target).prop('checked');
            $('.ys-page-item').prop('checked', isChecked);
        },

        /**
         * 更新全選狀態
         */
        updateSelectAllState: function () {
            const $items = $('.ys-page-item');
            const $checked = $items.filter(':checked');
            const $selectAll = $('#ys-select-all-pages');

            if ($checked.length === 0) {
                $selectAll.prop('checked', false).prop('indeterminate', false);
            } else if ($checked.length === $items.length) {
                $selectAll.prop('checked', true).prop('indeterminate', false);
            } else {
                $selectAll.prop('checked', false).prop('indeterminate', true);
            }
        }
    };

    /**
     * 預載入功能（在所有後台頁面執行）
     */
    const YSAdminCachePreload = {
        /**
         * 預載入佇列
         */
        queue: [],

        /**
         * 當前索引
         */
        currentIndex: 0,

        /**
         * 過期時間
         */
        expiration: 0,

        /**
         * 是否需要重新整理
         */
        refresh: 0,

        /**
         * 初始化
         *
         * @param {Array} urls 要預載入的 URL 列表
         */
        init: function (urls) {
            if (!urls || urls.length === 0) {
                return;
            }

            this.queue = urls;

            // 優先處理控制台（檢查完整 URL 中是否包含 index.php）
            const dashboardIndex = urls.findIndex(function(url) {
                return url.indexOf('/index.php') !== -1 || url.endsWith('/wp-admin/');
            });
            if (dashboardIndex > 0) {
                const dashboardUrl = this.queue.splice(dashboardIndex, 1)[0];
                this.queue.unshift(dashboardUrl);
            }

            // 延遲開始預載入（避免影響頁面載入）
            setTimeout(this.processQueue.bind(this), 2000);
        },

        /**
         * 處理佇列
         */
        processQueue: function () {
            if (this.queue.length === 0) {
                return;
            }

            // 所有項目處理完成，等待後重新開始
            if (this.currentIndex >= this.queue.length) {
                this.currentIndex = 0;
                const delay = Math.max(this.expiration - 10000, 2000);
                this.expiration = 0;
                this.refresh = 1;
                setTimeout(this.processQueue.bind(this), delay);
                return;
            }

            const url = this.queue[this.currentIndex];
            const self = this;

            $.ajax({
                url: url,
                method: 'POST',
                data: {
                    ys_admin_cache_prefetch: 1,
                    ys_admin_cache_refresh: this.refresh
                },
                success: function (data) {
                    if (typeof data === 'string' &&
                        (data.indexOf('prefetched') === 0 || data.indexOf('prefetching') === 0)) {
                        const exp = parseInt(data.split(':')[1], 10) * 1000;
                        if (exp < self.expiration || self.expiration === 0) {
                            self.expiration = exp;
                        }
                    }
                },
                complete: function () {
                    self.currentIndex++;
                    // 短暫延遲後處理下一個
                    setTimeout(self.processQueue.bind(self), 100);
                }
            });
        }
    };

    // DOM Ready
    $(function () {
        // 設定頁面初始化
        if ($('.ys-admin-cache-settings').length) {
            YSAdminCacheSettings.init();
        }
    });

    // 暴露預載入功能供外部呼叫
    window.ys_admin_cache_prefetch = function (urls) {
        YSAdminCachePreload.init(urls);
    };

})(jQuery);
