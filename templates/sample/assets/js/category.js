document.addEventListener('DOMContentLoaded', function () {
  const container = document.getElementById('category-files');
  if (!container) {
    return;
  }

  const items = Array.from(container.querySelectorAll('.file-item'));
  const perPage = parseInt(container.dataset.perPage, 10) || 10;
  const totalPages = Math.ceil(items.length / perPage);
  const pagination = document.getElementById('pagination');
  let currentPage = 1;

  function showPage(page) {
    const start = (page - 1) * perPage;
    const end = start + perPage;
    items.forEach((item, index) => {
      item.style.display = index >= start && index < end ? '' : 'none';
    });
    currentPage = page;
    updatePagination();
  }

  function makeButton(label, onClick) {
    const btn = document.createElement('button');
    btn.textContent = label;
    btn.classList.add('pagination-btn');
    btn.addEventListener('click', onClick);
    return btn;
  }

  function updatePagination() {
    pagination.replaceChildren();

    if (totalPages <= 1) {
      return;
    }

    if (currentPage > 1) {
      pagination.appendChild(makeButton('Previous', () => showPage(currentPage - 1)));
    }

    // Page numbers (show max 7 buttons: first, ..., current-1, current, current+1, ..., last)
    const pagesToShow = [];

    if (totalPages <= 7) {
      for (let i = 1; i <= totalPages; i++) {
        pagesToShow.push(i);
      }
    } else {
      pagesToShow.push(1);

      if (currentPage > 3) {
        pagesToShow.push('...');
      }

      for (let i = Math.max(2, currentPage - 1); i <= Math.min(totalPages - 1, currentPage + 1); i++) {
        if (!pagesToShow.includes(i)) {
          pagesToShow.push(i);
        }
      }

      if (currentPage < totalPages - 2) {
        pagesToShow.push('...');
      }

      if (!pagesToShow.includes(totalPages)) {
        pagesToShow.push(totalPages);
      }
    }

    pagesToShow.forEach(page => {
      if (page === '...') {
        const ellipsis = document.createElement('span');
        ellipsis.textContent = '...';
        ellipsis.classList.add('pagination-ellipsis');
        pagination.appendChild(ellipsis);
        return;
      }

      const btn = makeButton(String(page), () => showPage(page));
      btn.classList.toggle('active', page === currentPage);
      pagination.appendChild(btn);
    });

    if (currentPage < totalPages) {
      pagination.appendChild(makeButton('Next', () => showPage(currentPage + 1)));
    }
  }

  showPage(1);
});
