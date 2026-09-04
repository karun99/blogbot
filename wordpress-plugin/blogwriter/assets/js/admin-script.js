/* global jQuery, blogwriter */
(function ($) {
    'use strict';

    $(function () {
        // Live "Run Now" confirmation on the jobs list.
        $('.bw-row-actions a[href*="action=run-now"]').on('click', function () {
            return window.confirm('Run this job now? This will generate posts immediately.');
        });

        // Show a small toast when a job is created.
        if (window.location.search.indexOf('created=') !== -1) {
            var notice = document.createElement('div');
            notice.className = 'notice notice-success is-dismissible';
            notice.style.position = 'fixed';
            notice.style.right = '20px';
            notice.style.bottom = '20px';
            notice.style.zIndex = '99999';
            notice.textContent = 'Job created successfully.';
            document.body.appendChild(notice);
            setTimeout(function () {
                notice.remove();
            }, 3500);
        }
    });
})(jQuery);
