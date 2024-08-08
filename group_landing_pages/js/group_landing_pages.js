(function ($, Drupal) {

  Drupal.behaviors.group_landing_pages = {
    attach: function (context, settings) {
      once('attach-blog-ajax', 'html', context).forEach(
        function() {
          $(document).ajaxComplete(function (event, xhr, settings) {
            if (settings.url && settings.url.match(/^\/group_landing_pages\/modal_blog_form/)) {
              $('.view-id-group_blog_entries').trigger('RefreshView');
            }
          });
        }
      );
    }
  };

})(jQuery, Drupal);
