(function ($) {
  "use strict";

  const WBPIProgress = {
    batchKey: null,
    timer: null,

    init() {
      this.bindActions();
      this.start();
    },

    start() {
      this.fetch();

      if (this.timer) {
        clearInterval(this.timer);
      }

      this.timer = setInterval(() => {
        this.fetch();
      }, 2000);
    },

    request(action) {
      return $.post(WBPI.ajax, {
        action,
        nonce: WBPI.nonce,
        batch_key: this.batchKey,
      });
    },

    bindActions() {
      $(document).on("click", "#wbpi-pause", () => {
        this.request("wbpi_pause_batch").done(() => {
          this.fetch();
        });
      });

      $(document).on("click", "#wbpi-resume", () => {
        this.request("wbpi_resume_batch").done(() => {
          this.fetch();
        });
      });

      $(document).on("click", "#wbpi-stop", () => {
        if (!confirm("Stop this import?")) {
          return;
        }

        this.request("wbpi_stop_batch").done(() => {
          this.fetch();
        });
      });

      $(document).on("click", "#wbpi-restart", () => {
        this.request("wbpi_restart_batch").done(() => {
          this.fetch();
        });
      });
    },

    fetch() {
      $.post(
        WBPI.ajax,
        {
          action: "wbpi_progress",
          nonce: WBPI.nonce,
        },
        (response) => {
          if (!response.success) {
            this.empty();
            return;
          }

          const data = response.data;

          if (!data.running) {
            this.empty();
            return;
          }

          this.batchKey = data.batch_key;

          this.render(data);
          this.toggleButtons(data.status);
        },
      );
    },

    render(data) {
      $("#wbpi-progress-container")
        .removeClass("processing completed paused cancelled failed")
        .addClass(data.status);

      $("#wbpi-progress-text").html(
        `<span>${this.ucfirst(data.status)}</span><span>${data.percentage}%</span>`,
      );

      $("#wbpi-progress-bar span").css("width", data.percentage + "%");

      $(".wbpi-count").text(`${data.processed} / ${data.total}`);

      $(".wbpi-speed").text(`${data.speed} / min`);

      $(".wbpi-eta").text(
        data.eta > 0 ? `ETA ${this.seconds(data.eta)}` : "ETA --",
      );

      if (data.status === "completed" || data.status === "cancelled") {
        setTimeout(() => {
          this.fetch();
        }, 2000);
      }
    },

    toggleButtons(status) {
      $("#wbpi-pause").prop("disabled", status !== "processing");

      $("#wbpi-resume").prop("disabled", status !== "paused");

      $("#wbpi-stop").prop(
        "disabled",
        status === "completed" || status === "cancelled",
      );
    },

    empty() {
      $("#wbpi-progress-container").removeClass(
        "processing completed paused cancelled failed",
      );

      $("#wbpi-progress-text").html(
        "<span>No Active Import</span><span>0%</span>",
      );

      $("#wbpi-progress-bar span").css("width", "0%");

      $(".wbpi-count").text("0 / 0");
      $(".wbpi-speed").text("0 / min");
      $(".wbpi-eta").text("ETA --");
      this.toggleButtons("");
    },

    seconds(seconds) {
      seconds = parseInt(seconds);

      const h = Math.floor(seconds / 3600);
      const m = Math.floor((seconds % 3600) / 60);
      const s = seconds % 60;

      if (h > 0) {
        return `${h}h ${m}m`;
      }

      if (m > 0) {
        return `${m}m ${s}s`;
      }

      return `${s}s`;
    },

    ucfirst(text) {
      if (!text) {
        return "";
      }

      return text.charAt(0).toUpperCase() + text.slice(1);
    },
  };

  $(function () {
    WBPIProgress.init();
  });
})(jQuery);
