"use strict";(function(a){a.behaviors.heromedia={attach:function attach(){// Does the calculation of the element position and the time being clicked
function a(a,b,c,d,e,f,g){if(a){var h=new Date().getTime(),i=setInterval(function(){var j=Math.min(1,(new Date().getTime()-h)/f);g?a[b]=d+j*(e-d)+c:a.style[b]=d+j*(e-d)+c,1===j&&clearInterval(i)},10);g?a[b]=d+c:a.style[b]=d+c}}var b=document.querySelectorAll(".hero-media");// Adds JS class to each one of the existing building blocks
b.forEach(function(b){b.classList.contains("js-hero-media")||b.classList.add("js-hero-media");var c=b.querySelector(".hero-media__more");c.classList.contains("js-hero-media__more")||c.classList.add("js-hero-media__more");// Target Read More button inside Hero Media
var d=b.querySelector(".js-hero-media__more");d.onclick=function(b){// Gets button parent
var c=b.target.closest("div.building-block"),d=c.nextElementSibling;// Gets next building block
d&&a(document.scrollingElement||document.documentElement,"scrollTop","",0,d.offsetTop,0,!0)}})}}})(Drupal);
//# sourceMappingURL=hero-media.js.map
