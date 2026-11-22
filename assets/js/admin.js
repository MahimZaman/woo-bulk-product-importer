(function ($) {
  "use strict";

  const WBPIAdmin = {
    init() {
      this.bindEvents();
    },

    bindEvents() {
      $(document)
        .on("click", ".wbpi-btn", this.buttonLoading)
        .on("submit", "form", this.formLoading);
    },

    buttonLoading() {
      const $button = $(this);

      if ($button.hasClass("wbpi-loading")) {
        return;
      }

      $button.addClass("wbpi-loading").prop("disabled", true);
    },

    formLoading() {
      $(this)
        .find('button[type="submit"],input[type="submit"]')
        .addClass("wbpi-loading")
        .prop("disabled", true);
    },

    request(action, data = {}) {
      return $.ajax({
        url: WBPI.ajax,
        type: "POST",
        dataType: "json",
        data: {
          action,
          nonce: WBPI.nonce,
          ...data,
        },
      });
    },

    notify(message, type = "success") {
      let noticeClass = "notice-success";

      if (type === "error") {
        noticeClass = "notice-error";
      }

      if (type === "warning") {
        noticeClass = "notice-warning";
      }

      $(".wbpi-admin-notice").remove();

      $(
        '<div class="notice is-dismissible wbpi-admin-notice ' +
          noticeClass +
          '"><p>' +
          message +
          "</p></div>",
      ).insertAfter(".wrap h1:first");
    },
  };

  window.WBPIAdmin = WBPIAdmin;

  $(function () {
    WBPIAdmin.init();
  });
})(jQuery);
