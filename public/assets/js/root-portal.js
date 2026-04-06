const prefersReducedMotion = window.matchMedia("(prefers-reduced-motion: reduce)").matches;

if (!prefersReducedMotion) {
  const revealTargets = document.querySelectorAll(".hero-copy, .portal-card, .steps-section, .entry-section");

  revealTargets.forEach((node) => node.classList.add("is-reveal"));

  const observer = new IntersectionObserver((entries) => {
    entries.forEach((entry) => {
      if (!entry.isIntersecting) {
        return;
      }

      entry.target.classList.add("is-visible");
      observer.unobserve(entry.target);
    });
  }, {
    threshold: 0.18
  });

  revealTargets.forEach((node) => observer.observe(node));
}
