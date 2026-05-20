/**
 * Stan nagłówka przy przewijaniu.
 *
 * Dodaje klasę `sa` do elementu(ów) `.header`, gdy użytkownik przewinie
 * stronę poniżej 5% wysokości okna; usuwa ją po powrocie na górę.
 * Działa wyłącznie na ekranach >= 768px (na mniejszych klasa nigdy nie
 * jest dodawana).
 *
 * Zastępuje wcześniejszą implementację opartą na GSAP + ScrollTrigger
 * (~117 KB JS) — sam przełącznik klasy nie wymagał całej biblioteki.
 * Style przejścia dla `.sa` pozostają bez zmian (pochodzą z Elementora).
 */
(function () {
	'use strict';

	var headers = document.querySelectorAll('.header');
	if (!headers.length) {
		return;
	}

	var mq = window.matchMedia('(min-width: 768px)');
	var ticking = false;

	function update() {
		ticking = false;
		var active = mq.matches && window.scrollY > window.innerHeight * 0.05;
		for (var i = 0; i < headers.length; i++) {
			headers[i].classList.toggle('sa', active);
		}
	}

	function onScroll() {
		if (!ticking) {
			ticking = true;
			window.requestAnimationFrame(update);
		}
	}

	window.addEventListener('scroll', onScroll, { passive: true });
	window.addEventListener('resize', onScroll);

	update();
})();
