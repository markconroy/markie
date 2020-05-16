(function <%= dashlessName %>Script(Drupal, drupalSettings) {
  Drupal.behaviors.<%= dashlessName %> = {
    attach(context) {
      context = context || document;

      // Add class to element to ensure the script only runs once.
      if (!document.querySelector('.<%= dashlessName %>').classList.contains('js-<%= dashlessName %>')) {
        const our<%= dashlessName %> = document.querySelector('.<%= dashlessName %>');
        our<%= dashlessName %>.classList.add('js-<%= dashlessName %>-processed');

        // Start your JS Here
        
      }
    },
  };
}(Drupal, drupalSettings));
