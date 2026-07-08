// 削除等の確認ダイアログ（S-06 ほか）。form[data-confirm] の送信時に確認する
document.addEventListener('submit', (event) => {
  const form = event.target;
  if (form instanceof HTMLFormElement && form.dataset.confirm !== undefined) {
    if (!window.confirm(form.dataset.confirm || '実行してよろしいですか？')) {
      event.preventDefault();
    }
  }
});
