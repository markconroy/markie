(function heromediaScript(Drupal) {
  Drupal.behaviors.heromedia = {
    attach(context) {
      const heroBlocks = document.querySelectorAll('.hero-media');

      // Adds JS class to each one of the existing building blocks
      heroBlocks.forEach(heroBlock => {
        if (!heroBlock.classList.contains('js-hero-media')) {
          heroBlock.classList.add('js-hero-media');
        }

        const heroReadMore = heroBlock.querySelector('.hero-media__more');
        if (!heroReadMore.classList.contains('js-hero-media__more')) {
          heroReadMore.classList.add('js-hero-media__more');
        }

        // Target Read More button inside Hero Media
        const readMoreBtn = heroBlock.querySelector('.js-hero-media__more');

        readMoreBtn.onclick = (ev) => {
          // Gets button parent
          const readMoreBtnParent = ev.target.closest('div.building-block');
          // Gets next building block
          const scrollTarget = readMoreBtnParent.nextElementSibling;

          // Checks if there is actually a Building block next to it
          if (scrollTarget) {
            // Calls function previously declared
            animateScroll(document.scrollingElement || document.documentElement, 'scrollTop', '', 0, scrollTarget.offsetTop, 0, true);
          }
        }
      });

      // Does the calculation of the element position and the time being clicked
      function animateScroll(elem, style, unit, from, to, time, prop) {
        if (!elem) {
            return;
        }
        var start = new Date().getTime(),
        timer = setInterval(function () {
            var step = Math.min(1, (new Date().getTime() - start) / time);
            if (prop) {
                elem[style] = (from + step * (to - from))+unit;
            } else {
                elem.style[style] = (from + step * (to - from))+unit;
            }
            if (step === 1) {
                clearInterval(timer);
            }
        }, 10);
        if (prop) {
              elem[style] = from+unit;
        } else {
              elem.style[style] = from+unit;
        }
      }

    }
  };
})(Drupal);
