(function(){
    function scrollMessagesToBottom() {
        var msgBox = document.getElementById('ai-chatbot-widget-messages');
        if (msgBox) msgBox.scrollTop = msgBox.scrollHeight;
    }

    document.addEventListener('DOMContentLoaded', function() {
        var btnContent = document.getElementById('ai-chatbot-widget-button-content');
        var btn = document.getElementById('ai-chatbot-widget-button');
        var msg = document.getElementById('ai-chatbot-widget-button-message');
        var win = document.getElementById('ai-chatbot-widget-window');
        var cls = document.getElementById('ai-chatbot-widget-header-close');

        function open()
        {
            win.style.display = 'block';
            void win.offsetWidth;
            win.classList.remove('ai-chatbot-widget-close');
            btnContent.classList.remove('ai-chatbot-widget-close');
            win.classList.add('ai-chatbot-widget-open');
            btnContent.classList.add('ai-chatbot-widget-open');
            scrollMessagesToBottom();
        }
        
        function close()
        {
            if (win.classList.contains('ai-chatbot-widget-open')) {
                win.classList.remove('ai-chatbot-widget-open');
                btnContent.classList.remove('ai-chatbot-widget-open');
                win.classList.add('ai-chatbot-widget-close');
                btnContent.classList.add('ai-chatbot-widget-close');
                setTimeout(function(){
                    win.style.display = 'none';
                }, 250);
                return true;
            }
            return false;
        }

        if (btnContent.classList.contains('ai-chatbot-open'))
        {
            setTimeout(open, 1000);
        }
        

        if (cls) {
            cls.addEventListener('click', close);
        }

        if (btn && win) {
            btn.addEventListener('click', function() {
                if (!close())
                    open();
            });
        }

        if (msg && win) {
            msg.addEventListener('click', function() {
                if (!close())
                    open();
            });
        }

        var form = document.getElementById('ai-chatbot-widget-form');
        if (form) {
            form.addEventListener('submit', function(e) {
                e.preventDefault();
                var input = document.getElementById('ai-chatbot-widget-input');
                var msg = input.value.trim();
                if (!msg) return;
                var messages = document.getElementById('ai-chatbot-widget-messages');
                var userMsg = document.createElement('div');
                userMsg.className = 'ai-chatbot-message ai-chatbot-user-message';
                if (ai_chatbot_widget.speech == "friendly")
                {
                    userMsg.innerHTML = '<strong>Jij:</strong> ' + msg;
                }
                else
                {
                    userMsg.innerHTML = '<strong>U:</strong> ' + msg;
                }
                messages.appendChild(userMsg);
                input.value = '';
                scrollMessagesToBottom();
                // Show loading
                var loading = document.createElement('div');
                loading.className = 'ai-chatbot-message ai-chatbot-bot-message';
                loading.textContent = '...';
                messages.appendChild(loading);
                scrollMessagesToBottom();
                // Ajax
                var xhr = new XMLHttpRequest();
                xhr.open('POST', ai_chatbot_widget.ajaxurl, true);
                xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
                xhr.onload = function() {
                    messages.removeChild(loading);
                    if (xhr.status === 200) {
                        try {
                            var res = JSON.parse(xhr.responseText);
                            if (res.success && res.data && res.data.answer) {
                                var botMsg = document.createElement('div');
                                botMsg.className = 'ai-chatbot-message ai-chatbot-bot-message';
                                botMsg.innerHTML = '<strong>Assistent:</strong> ' + document.createTextNode(res.data.answer).textContent;
                                messages.appendChild(botMsg);
                            } else {
                                var errMsg = document.createElement('div');
                                errMsg.className = 'ai-chatbot-message ai-chatbot-bot-message';
                                errMsg.innerHTML = '<strong>Assistent:</strong> Geen antwoord gevonden.';
                                messages.appendChild(errMsg);
                            }
                        } catch(e) {
                            var errMsg = document.createElement('div');
                            errMsg.className = 'ai-chatbot-message ai-chatbot-bot-message';
                            errMsg.innerHTML = '<strong>Assistent:</strong> Fout.';
                            messages.appendChild(errMsg);
                        }
                    } else {
                        var errMsg = document.createElement('div');
                        errMsg.className = 'ai-chatbot-message ai-chatbot-bot-message';
                        errMsg.innerHTML = '<strong>Assistent:</strong> Serverfout.';
                        messages.appendChild(errMsg);
                    }
                    scrollMessagesToBottom();
                };
                xhr.send('action=ai_chatbot_search&question=' + encodeURIComponent(msg));
            });
        }
    });
})();
