(function accordionScript(Drupal) {
  Drupal.behaviors.accordion = {
    attach(context) {
      // Get all Accordion headings.
      const headings = context.querySelectorAll(".accordion__trigger")

      Array.prototype.forEach.call(headings, (heading, index) => {
        let parent = heading.parentNode
        let content = heading.nextElementSibling

        // Give each <h2> a toggle button child
        // with the SVG arrow icon
        heading.innerHTML = `
          <button aria-expanded="false" class="accordion__trigger-button">
            ${heading.textContent}
            <svg class="accordion__trigger-icon" aria-hidden="true" focusable="false" width="48" height="24" viewBox="0 0 48 24" xmlns="http://www.w3.org/2000/svg"><path d="M45.14 1.019c.655-.583 1.719-.583 2.373 0 .65.586.65 1.539 0 2.125L25.186 23.145c-.654.583-1.718.583-2.372 0L.487 3.144a1.402 1.402 0 010-2.125c.654-.583 1.718-.583 2.373 0l21.153 18.954L45.14 1.02z" fill="#4A4A4A" fill-rule="evenodd"/></svg>
          </button>
        `
        let btn = heading.querySelector('button.accordion__trigger-button')

        if (index === 0) {
          // Set first accordion to expanded
          btn.setAttribute('aria-expanded', true)
          parent.classList.add('is-active')
          content.setAttribute('aria-hidden', false)
        } else {
          // Hide other accordion item's content from screen-readers
          content.setAttribute('aria-hidden', true)
        }

        btn.onclick = () => {
          let expanded = btn.getAttribute('aria-expanded') === 'true' || false
          // Switch the state
          btn.setAttribute('aria-expanded', !expanded)
          // Switch the content's visibility
          parent.classList.toggle('is-active')
          content.setAttribute('aria-hidden', expanded)
        }

        // Hide Accordion content via css only if js
        parent.classList.add('js-accordion')
      });
    }
  };
}(Drupal));
