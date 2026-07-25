/**
 * Lightweight rich-text editor for blog post content (#content).
 */
(function () {
  'use strict';

  const COLORS = [
    { label: 'Default', value: '#1E293B' },
    { label: 'Blue', value: '#0B5ED7' },
    { label: 'Navy', value: '#072E70' },
    { label: 'Green', value: '#198754' },
    { label: 'Gold', value: '#B8941F' },
    { label: 'Red', value: '#B42318' },
    { label: 'Gray', value: '#64748B' },
  ];

  function escapeHtml(value) {
    return String(value ?? '')
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;');
  }

  function plainToHtml(text) {
    const raw = String(text || '').replace(/\r\n/g, '\n').replace(/\r/g, '\n').trim();
    if (!raw) return '<p><br></p>';
    if (/<[a-z][\s\S]*>/i.test(raw)) return raw;
    return raw
      .split(/\n\s*\n/)
      .map((block) => {
        const lines = escapeHtml(block.trim()).replace(/\n/g, '<br>');
        return '<p>' + lines + '</p>';
      })
      .join('');
  }

  function buildToolbar() {
    const bar = document.createElement('div');
    bar.className = 'rte-toolbar';
    bar.setAttribute('role', 'toolbar');
    bar.setAttribute('aria-label', 'Text formatting');

    const groups = [
      [
        { cmd: 'bold', title: 'Bold', html: '<b>B</b>' },
        { cmd: 'italic', title: 'Italic', html: '<i>I</i>' },
        { cmd: 'underline', title: 'Underline', html: '<u>U</u>' },
        { cmd: 'strikeThrough', title: 'Strikethrough', html: '<s>S</s>' },
      ],
      [
        { cmd: 'formatBlock', value: 'H2', title: 'Heading', html: 'H2' },
        { cmd: 'formatBlock', value: 'H3', title: 'Subheading', html: 'H3' },
        { cmd: 'formatBlock', value: 'P', title: 'Paragraph', html: 'P' },
      ],
      [
        { cmd: 'insertUnorderedList', title: 'Bullet list', html: '• List' },
        { cmd: 'insertOrderedList', title: 'Numbered list', html: '1. List' },
        { cmd: 'formatBlock', value: 'BLOCKQUOTE', title: 'Quote', html: 'Quote' },
      ],
      [
        { cmd: 'justifyLeft', title: 'Align left', html: 'Left' },
        { cmd: 'justifyCenter', title: 'Align center', html: 'Center' },
        { cmd: 'justifyRight', title: 'Align right', html: 'Right' },
      ],
      [
        { cmd: 'createLink', title: 'Insert link', html: 'Link' },
        { cmd: 'removeFormat', title: 'Clear formatting', html: 'Clear' },
      ],
    ];

    groups.forEach((group, gi) => {
      if (gi > 0) {
        const sep = document.createElement('span');
        sep.className = 'rte-sep';
        sep.setAttribute('aria-hidden', 'true');
        bar.appendChild(sep);
      }
      group.forEach((item) => {
        const btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'rte-btn';
        btn.title = item.title;
        btn.setAttribute('aria-label', item.title);
        btn.dataset.cmd = item.cmd;
        if (item.value) btn.dataset.value = item.value;
        btn.innerHTML = item.html;
        bar.appendChild(btn);
      });
    });

    const sep = document.createElement('span');
    sep.className = 'rte-sep';
    sep.setAttribute('aria-hidden', 'true');
    bar.appendChild(sep);

    const colorWrap = document.createElement('label');
    colorWrap.className = 'rte-color';
    colorWrap.title = 'Text color';
    colorWrap.innerHTML = '<span>Color</span>';
    const colorSelect = document.createElement('select');
    colorSelect.className = 'rte-color-select';
    colorSelect.setAttribute('aria-label', 'Text color');
    COLORS.forEach((c) => {
      const opt = document.createElement('option');
      opt.value = c.value;
      opt.textContent = c.label;
      opt.style.color = c.value;
      colorSelect.appendChild(opt);
    });
    colorWrap.appendChild(colorSelect);
    bar.appendChild(colorWrap);

    return { bar, colorSelect };
  }

  function initEditor(textarea) {
    if (!textarea || textarea.dataset.rteReady === '1') return;
    textarea.dataset.rteReady = '1';

    const wrap = document.createElement('div');
    wrap.className = 'rte-wrap';

    const { bar, colorSelect } = buildToolbar();
    const editor = document.createElement('div');
    editor.className = 'rte-editor content-box';
    editor.contentEditable = 'true';
    editor.setAttribute('role', 'textbox');
    editor.setAttribute('aria-multiline', 'true');
    editor.setAttribute('aria-label', 'Article content');
    editor.innerHTML = plainToHtml(textarea.value);

    textarea.classList.add('rte-source');
    textarea.tabIndex = -1;
    textarea.setAttribute('aria-hidden', 'true');

    textarea.parentNode.insertBefore(wrap, textarea);
    wrap.appendChild(bar);
    wrap.appendChild(editor);
    wrap.appendChild(textarea);

    const sync = () => {
      const html = editor.innerHTML.trim();
      textarea.value = html === '' || html === '<br>' || html === '<p><br></p>' ? '' : editor.innerHTML;
    };

    const run = (cmd, value) => {
      editor.focus();
      try {
        document.execCommand('styleWithCSS', false, true);
      } catch (_) { /* ignore */ }
      if (cmd === 'createLink') {
        const url = window.prompt('Enter link URL (https://…)', 'https://');
        if (!url) return;
        document.execCommand('createLink', false, url);
      } else if (cmd === 'formatBlock') {
        document.execCommand('formatBlock', false, value);
      } else {
        document.execCommand(cmd, false, value || null);
      }
      sync();
    };

    bar.addEventListener('mousedown', (e) => {
      const btn = e.target.closest('.rte-btn');
      if (!btn) return;
      e.preventDefault();
      run(btn.dataset.cmd, btn.dataset.value || null);
    });

    colorSelect.addEventListener('change', () => {
      run('foreColor', colorSelect.value);
    });

    editor.addEventListener('input', sync);
    editor.addEventListener('blur', sync);

    const form = textarea.closest('form');
    form?.addEventListener('submit', () => {
      sync();
      if (!textarea.value.trim()) {
        editor.focus();
      }
    });

    // Keep browser required validation working via textarea value
    textarea.addEventListener('invalid', () => {
      editor.classList.add('is-invalid');
      editor.focus();
    });
    editor.addEventListener('input', () => editor.classList.remove('is-invalid'));
  }

  document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('textarea#content.content-box, textarea.content-box#content').forEach(initEditor);
  });
})();
