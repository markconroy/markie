var accordionTrigger = document.getElementsByClassName("accordion__trigger");
var i;

for (i = 0; i < accordionTrigger.length; i++) {
  accordionTrigger[i].addEventListener("click", function() {
    this.classList.toggle("active");
    var accordionResponse = this.nextElementSibling;
    if (accordionResponse.style.maxHeight) {
      accordionResponse.style.maxHeight = null;
    } else {
      accordionResponse.style.maxHeight = accordionResponse.scrollHeight + "px";
    }
  });
}
