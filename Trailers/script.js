(function(){
      /* -----------------------
         Elementos clave
         ----------------------- */
      const openBtn = document.getElementById('openNav');
      const closeBtn = document.getElementById('closeNav');
      const sideNav = document.getElementById('sideNav');
      const overlay = document.getElementById('overlay');
      const app = document.getElementById('app');

      /* -----------------------
         Abrir / cerrar menú lateral
         - Mantener aria-expanded y aria-hidden
         - Soportar ESC para cerrar
         ----------------------- */
      function openNav(){
        sideNav.classList.add('open');
        overlay.classList.add('show');
        openBtn.setAttribute('aria-expanded','true');
        sideNav.setAttribute('aria-hidden','false');
        overlay.setAttribute('aria-hidden','false');
        // Trapping focus no implementado completo, pero damos foco al nav
        sideNav.focus && sideNav.focus();
      }
      function closeNav(){
        sideNav.classList.remove('open');
        overlay.classList.remove('show');
        openBtn.setAttribute('aria-expanded','false');
        sideNav.setAttribute('aria-hidden','true');
        overlay.setAttribute('aria-hidden','true');
        openBtn.focus();
      }

      openBtn.addEventListener('click', openNav);
      closeBtn && closeBtn.addEventListener('click', closeNav);
      overlay.addEventListener('click', closeNav);
      document.addEventListener('keydown', (e) => {
        if(e.key === 'Escape') closeNav();
      });

      /* -----------------------
         Formulario: validación mínima
         ----------------------- */
      const form = document.getElementById('contactForm');
      const feedback = document.getElementById('formFeedback');
      form.addEventListener('submit', function(e){
        e.preventDefault();
        feedback.textContent = '';
        const nombre = form.nombre.value.trim();
        const email = form.email.value.trim();
        if(!nombre || !email){
          feedback.textContent = 'Por favor completá nombre y email.';
          return;
        }
        // Simular envío (acá podés integrar Google Sheets / API)
        feedback.textContent = 'Gracias! Tu comentario se envió correctamente.';
        form.reset();
        setTimeout(()=> feedback.textContent = '', 4000);
      });

      /* -----------------------
         Funciones auxiliares: lightbox / carrito (simples)
         ----------------------- */
      window.openLightbox = function(src){
        // simple: abrir imagen en nueva pestaña. Podés reemplazar por modal.
        window.open(src, '_blank');
      };
      window.addToCart = function(item){
        // simple notificación
        alert('Pedido agregado: ' + item + '\nContactanos para finalizar la compra.');
      };

      /* -----------------------
         Mejora: cerrar detalles abiertos al cambiar viewport
         ----------------------- */
      function closeDetailsOnDesktop(){
        const details = sideNav.querySelectorAll('details');
        if(window.innerWidth > 900){
          details.forEach(d => d.removeAttribute('open'));
        }
      }
      window.addEventListener('resize', closeDetailsOnDesktop);
      // inicial
      closeDetailsOnDesktop();

      /* -----------------------
         Accesibilidad extra:
         - Permitir foco en sideNav
         ----------------------- */
      sideNav.setAttribute('tabindex','-1');
      overlay.setAttribute('tabindex','-1');

      /* -----------------------
         Prevent keyboard traps while side nav closed
         ----------------------- */
      // (para implementaciones más serias usar focus-trap library)
    })();