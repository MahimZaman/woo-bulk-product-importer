(function ($) {
  "use strict";

  const WBPILogs = {
    init() {
      this.bindEvents();
      this.loadLogs();
    },

    bindEvents() {
      $(document).on("click", "#wbpi-refresh-logs", () => {
        this.loadLogs();
      });

      $(document).on("click", "#wbpi-clear-logs", () => {
        this.clearLogs();
      });
    },

    loadLogs() {
      $.ajax({
        url: WBPI.ajax,
        type: "POST",
        dataType: "json",
        data: {
          action: "wbpi_get_logs",
          nonce: WBPI.nonce,
        },
        success: (response) => {
          if (!response.success) {
            return;
          }

          $("#wbpi-log-viewer").text(response.data.logs.join("\n\n"));

          this.updateStats(response.data.stats);
        },
      });
    },

    clearLogs() {
      if (!confirm("Are you sure you want to clear all logs?")) {
        return;
      }

      $.ajax({
        url: WBPI.ajax,
        type: "POST",
        dataType: "json",
        data: {
          action: "wbpi_clear_logs",
          nonce: WBPI.nonce,
        },
        success: (response) => {
          if (!response.success) {
            return;
          }

          $("#wbpi-log-viewer").empty();

          this.loadLogs();

          if (window.WBPIAdmin) {
            WBPIAdmin.notify(response.data.message);
          }
        },
      });
    },

    updateStats(stats) {
      const cards = $(".wbpi-stat-card strong");

      if (cards.length < 5) {
        return;
      }

      $(cards[0]).text(stats.total);
      $(cards[1]).text(stats.info);
      $(cards[2]).text(stats.warning);
      $(cards[3]).text(stats.error);
      $(cards[4]).text(stats.fatal);
    },
  };

  $(function () {
    if ($("#wbpi-log-viewer").length) {
      WBPILogs.init();
    }
  });
})(jQuery);
