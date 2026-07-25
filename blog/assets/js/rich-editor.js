/**
 * Lightweight rich-text editor for blog post content (#content).
 * Uses semantic tags (strong/em/h2) so formatting survives server sanitization.
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
        const lines = escapeHtml(block.trim())
          .replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>')
          .replace(/\n/g, '<br>');
        return '<p>' + lines + '</p>';
      })
      .join('');
  }

  function normalizeEditorHtml(html) {
    const box = document.createElement('div');
    box.innerHTML = html;

    box.querySelectorAll('span').forEach((span) => {
      const style = (span.getAttribute('style') || '').toLowerCase();
      const colorMatch = style.match(/color\s*:\s*([^;]+)/);
      const bold = /font-weight\s*:\s*(bold|700|600)/.test(style);
      const italic = /font-style\s*:\s*italic/.test(style);
      const underline = /text-decoration[^;]*underline/.test(style);

      let node = span;
      const wrap = (tag) => {
        const el = document.createElement(tag);
        while (node.firstChild) el.appendChild(node.firstChild);
        node.replaceWith(el);
        node = el;
      };

      if (bold) wrap('strong');
      if (italic) wrap('em');
      if (underline) wrap('u');

      if (colorMatch) {
        const color = colorMatch[1].trim();
        if (node.tagName === 'SPAN') {
          node.setAttribute('style', 'color:' + color);
        } else {
          const colored = document.createElement('span');
          colored.setAttribute('style', 'color:' + color);
          while (node.firstChild) colored.appendChild(node.firstChild);
          node.appendChild(colored);
        }
      } else if (node.tagName === 'SPAN' && !node.getAttribute('style')) {
        const parent = node.parentNode;
        while (node.firstChild) parent.insertBefore(node.firstChild, node);
        parent.removeChild(node);
      }
    });

    box.querySelectorAll('b').forEach((el) => {
      const strong = document.createElement('strong');
      while (el.firstChild) strong.appendChild(el.firstChild);
      el.replaceWith(strong);
    });
    box.querySelectorAll('i').forEach((el) => {
      const em = document.createElement('em');
      while (el.firstChild) em.appendChild(el.firstChild);
      el.replaceWith(em);
    });

    return box.innerHTML;
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
        { cmd: 'formatBlock', value: 'h2', title: 'Heading', html: 'H2' },
        { cmd: 'formatBlock', value: 'h3', title: 'Subheading', html: 'H3' },
        { cmd: 'formatBlock', value: 'p', title: 'Paragraph', html: 'P' },
      ],
      [
        { cmd: 'insertUnorderedList', title: 'Bullet list', html: '• List' },
        { cmd: 'insertOrderedList', title: 'Numbered list', html: '1. List' },
        { cmd: 'formatBlock', value: 'blockquote', title: 'Quote', html: 'Quote' },
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

  function applyFormatBlock(tag) {
    const name = String(tag || 'p').replace(/[<>]/g, '').toLowerCase();
    const variants = ['<' + name + '>', name, name.toUpperCase(), '<' + name.toUpperCase() + '>'];
    for (let i = 0; i < variants.length; i++) {
      try {
        if (document.execCommand('formatBlock', false, variants[i])) return true;
      } catch (_) { /* try next */ }
    }
    return false;
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

    const sync = (normalize) => {
      const raw = normalize ? normalizeEditorHtml(editor.innerHTML) : editor.innerHTML;
      if (normalize && raw !== editor.innerHTML) {
        editor.innerHTML = raw;
      }
      const html = raw.trim();
      textarea.value = html === '' || html === '<br>' || html === '<p><br></p>' ? '' : raw;
    };

    const run = (cmd, value) => {
      editor.focus();
      if (cmd === 'foreColor') {
        try { document.execCommand('styleWithCSS', false, true); } catch (_) { /* ignore */ }
        document.execCommand('foreColor', false, value);
      } else if (cmd === 'createLink') {
        try { document.execCommand('styleWithCSS', false, false); } catch (_) { /* ignore */ }
        const url = window.prompt('Enter link URL (https://…)', 'https://');
        if (!url) return;
        document.execCommand('createLink', false, url);
      } else if (cmd === 'formatBlock') {
        try { document.execCommand('styleWithCSS', false, false); } catch (_) { /* ignore */ }
        applyFormatBlock(value);
      } else if (cmd === 'justifyLeft' || cmd === 'justifyCenter' || cmd === 'justifyRight') {
        try { document.execCommand('styleWithCSS', false, true); } catch (_) { /* ignore */ }
        document.execCommand(cmd, false, null);
      } else {
        // Prefer <strong>/<em>/<u> over styled spans
        try { document.execCommand('styleWithCSS', false, false); } catch (_) { /* ignore */ }
        document.execCommand(cmd, false, value || null);
      }
      sync(true);
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

    editor.addEventListener('input', () => {
      editor.classList.remove('is-invalid');
      sync(false);
    });
    editor.addEventListener('blur', () => sync(true));

    const form = textarea.closest('form');
    form?.addEventListener('submit', () => {
      sync(true);
      if (!textarea.value.trim()) {
        editor.focus();
      }
    });

    textarea.addEventListener('invalid', () => {
      editor.classList.add('is-invalid');
      editor.focus();
    });

    // Initial sync without rewriting caret position mid-edit
    sync(false);
  }

  document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('textarea#content.content-box, textarea.content-box#content').forEach(initEditor);
  });
})();
