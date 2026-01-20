$(document).ready(function() {
  const container = $('#category-files');
  const items = container.find('.file-item');
  const perPage = parseInt(container.data('per-page')) || 10;
  const totalPages = Math.ceil(items.length / perPage);
  let currentPage = 1;

  function showPage(page) {
    items.hide();
    const start = (page - 1) * perPage;
    const end = start + perPage;
    items.slice(start, end).show();
    currentPage = page;
    updatePagination();
  }

  function updatePagination() {
    const pagination = $('#pagination');
    pagination.empty();

    if (totalPages <= 1) return;

    // Previous button
    if (currentPage > 1) {
      pagination.append(
        $('<button>').text('Previous').addClass('pagination-btn').click(() => showPage(currentPage - 1))
      );
    }

    // Page numbers (show max 7 buttons: first, ..., current-1, current, current+1, ..., last)
    const pagesToShow = [];

    if (totalPages <= 7) {
      // Show all pages
      for (let i = 1; i <= totalPages; i++) {
        pagesToShow.push(i);
      }
    } else {
      // Always show first page
      pagesToShow.push(1);

      if (currentPage > 3) {
        pagesToShow.push('...');
      }

      // Show pages around current
      for (let i = Math.max(2, currentPage - 1); i <= Math.min(totalPages - 1, currentPage + 1); i++) {
        if (!pagesToShow.includes(i)) {
          pagesToShow.push(i);
        }
      }

      if (currentPage < totalPages - 2) {
        pagesToShow.push('...');
      }

      // Always show last page
      if (!pagesToShow.includes(totalPages)) {
        pagesToShow.push(totalPages);
      }
    }

    // Render page buttons
    pagesToShow.forEach(page => {
      if (page === '...') {
        pagination.append($('<span>').text('...').addClass('pagination-ellipsis'));
      } else {
        const btn = $('<button>')
          .text(page)
          .addClass('pagination-btn')
          .toggleClass('active', page === currentPage)
          .click(() => showPage(page));
        pagination.append(btn);
      }
    });

    // Next button
    if (currentPage < totalPages) {
      pagination.append(
        $('<button>').text('Next').addClass('pagination-btn').click(() => showPage(currentPage + 1))
      );
    }
  }

  // Initialize
  showPage(1);
});
