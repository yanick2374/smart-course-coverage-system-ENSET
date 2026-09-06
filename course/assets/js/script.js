document.addEventListener('DOMContentLoaded', () => {
  const sidebar = document.getElementById('sidebar');
  const open = document.getElementById('openSidebar');
  const close = document.getElementById('closeSidebar');
  if (open) open.addEventListener('click', () => sidebar.classList.add('open'));
  if (close) close.addEventListener('click', () => sidebar.classList.remove('open'));
  document.addEventListener('click', e => {
    if (window.innerWidth <= 800 && sidebar.classList.contains('open') &&
        !sidebar.contains(e.target) && e.target !== open) sidebar.classList.remove('open');
  });
});
