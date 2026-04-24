      </div>
    </section>
  </main>
  <script>
    (function () {
      var errorSummary = document.querySelector('[data-error-summary]');
      if (errorSummary) {
        errorSummary.setAttribute('tabindex', '-1');
        errorSummary.focus({ preventScroll: false });
      }

      document.querySelectorAll('[data-install-form]').forEach(function (form) {
        form.addEventListener('submit', function () {
          form.querySelectorAll('button[type="submit"]').forEach(function (button) {
            button.disabled = true;
            var loadingText = button.getAttribute('data-loading-text');
            if (loadingText) {
              button.textContent = loadingText;
            }
          });
        });
      });

      document.querySelectorAll('[data-password-toggle]').forEach(function (toggle) {
        toggle.addEventListener('click', function () {
          var target = document.getElementById(toggle.getAttribute('data-password-toggle'));
          if (!target) {
            return;
          }
          var nextType = target.type === 'password' ? 'text' : 'password';
          target.type = nextType;
          toggle.textContent = nextType === 'password' ? '显示' : '隐藏';
        });
      });

      document.querySelectorAll('[data-copy-target]').forEach(function (button) {
        button.addEventListener('click', function () {
          var target = document.getElementById(button.getAttribute('data-copy-target'));
          if (!target) {
            return;
          }
          var text = target.textContent || '';
          if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(text).then(function () {
              button.textContent = '已复制';
            });
          }
        });
      });
    })();
  </script>
</body>
</html>
