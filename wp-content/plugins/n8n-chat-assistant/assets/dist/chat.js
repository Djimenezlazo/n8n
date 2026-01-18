(() => {
  const settings = window.N8NChatSettings || {};
  const dictionary = {
    'es': {
      title: 'Asistente inteligente',
      subtitle: 'Pregúntame lo que necesites',
      inputPlaceholder: 'Escribe tu mensaje…',
      send: 'Enviar',
      name: 'Tu nombre (opcional)',
      email: 'Tu correo (opcional)',
      phone: 'Tu teléfono (opcional)',
      botGreeting: 'Hola, ¿en qué puedo ayudarte hoy?',
    },
    'es-CL': {
      title: 'Asistente inteligente',
      subtitle: 'Pregúntame lo que necesites',
      inputPlaceholder: 'Escribe tu mensaje…',
      send: 'Enviar',
      name: 'Tu nombre (opcional)',
      email: 'Tu correo (opcional)',
      phone: 'Tu teléfono (opcional)',
      botGreeting: 'Hola, ¿en qué puedo ayudarte hoy?',
    },
    'en': {
      title: 'Smart assistant',
      subtitle: 'Ask me anything',
      inputPlaceholder: 'Write your message…',
      send: 'Send',
      name: 'Your name (optional)',
      email: 'Your email (optional)',
      phone: 'Your phone (optional)',
      botGreeting: 'Hi! How can I help you today?',
    },
  };

  const getLang = (attr) => {
    if (attr) return attr;
    const lang = document.documentElement.lang || 'es';
    if (lang.toLowerCase().includes('es-cl')) return 'es-CL';
    if (lang.toLowerCase().includes('en')) return 'en';
    return 'es';
  };

  const widgets = document.querySelectorAll('.n8n-chat-widget');
  widgets.forEach((widget) => {
    const lang = getLang(widget.dataset.lang);
    const strings = dictionary[lang] || dictionary.es;
    const toggle = widget.querySelector('.n8n-chat-toggle');
    const panel = widget.querySelector('.n8n-chat-panel');
    const closeButton = widget.querySelector('.n8n-chat-close');
    const title = widget.querySelector('.n8n-chat-title');
    const subtitle = widget.querySelector('.n8n-chat-subtitle');
    const messagesEl = widget.querySelector('.n8n-chat-messages');
    const form = widget.querySelector('.n8n-chat-form');
    const input = widget.querySelector('.n8n-chat-input');
    const sendButton = widget.querySelector('.n8n-chat-send');
    const nameInput = widget.querySelector('.n8n-chat-name');
    const emailInput = widget.querySelector('.n8n-chat-email');
    const phoneInput = widget.querySelector('.n8n-chat-phone');

    let conversationId = null;

    title.textContent = strings.title;
    subtitle.textContent = strings.subtitle;
    input.placeholder = strings.inputPlaceholder;
    sendButton.textContent = strings.send;
    nameInput.placeholder = strings.name;
    emailInput.placeholder = strings.email;
    phoneInput.placeholder = strings.phone;

    const pushMessage = (role, content) => {
      const bubble = document.createElement('div');
      bubble.className = `n8n-chat-bubble n8n-chat-bubble--${role}`;
      bubble.textContent = content;
      messagesEl.appendChild(bubble);
      messagesEl.scrollTop = messagesEl.scrollHeight;
    };

    const openChat = () => {
      toggle.setAttribute('aria-expanded', 'true');
      panel.setAttribute('aria-hidden', 'false');
      panel.classList.add('is-open');
      if (window.gsap) {
        window.gsap.to(panel, { opacity: 1, y: -10, duration: 0.3 });
      }
      if (!messagesEl.children.length) {
        pushMessage('assistant', strings.botGreeting);
      }
    };

    const closeChat = () => {
      toggle.setAttribute('aria-expanded', 'false');
      panel.setAttribute('aria-hidden', 'true');
      panel.classList.remove('is-open');
    };

    toggle.addEventListener('click', () => {
      if (panel.classList.contains('is-open')) {
        closeChat();
      } else {
        openChat();
      }
    });

    closeButton.addEventListener('click', () => {
      closeChat();
    });

    form.addEventListener('submit', async (event) => {
      event.preventDefault();
      const message = input.value.trim();
      if (!message) return;
      pushMessage('user', message);
      input.value = '';

      const payload = {
        message,
        conversation_id: conversationId,
        user: {
          name: nameInput.value.trim(),
          email: emailInput.value.trim(),
          phone: phoneInput.value.trim(),
        },
        client: {
          user_agent: navigator.userAgent,
          language: navigator.language,
          platform: navigator.platform,
          browser: navigator.userAgentData?.brands?.map((b) => b.brand).join(', ') || '',
          ip: '',
        },
      };

      try {
        const response = await fetch(settings.endpoint, {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'X-WP-Nonce': settings.nonce,
          },
          body: JSON.stringify(payload),
        });
        const data = await response.json();
        if (data.conversation_id) {
          conversationId = data.conversation_id;
        }
        pushMessage('assistant', data.message || '');
      } catch (error) {
        pushMessage('assistant', 'No pudimos conectar con el asistente.');
      }
    });
  });
})();
