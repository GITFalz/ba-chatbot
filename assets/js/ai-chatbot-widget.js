(function(){
    function scrollMessagesToBottom() {
        var msgBox = document.getElementById('ai-chatbot-widget-messages');
        if (msgBox) msgBox.scrollTop = msgBox.scrollHeight;
    }

    function addReportButton(message) {
        var reportButton = document.createElement('button');
        reportButton.className = 'ai-chatbot-report-button';
        reportButton.type = 'button';
        reportButton.textContent = 'Meld een probleem met dit antwoord';
        message.appendChild(reportButton);
    }

    document.addEventListener('DOMContentLoaded', function() {
        var btnContent = document.getElementById('ai-chatbot-widget-button-content');
        var btn = document.getElementById('ai-chatbot-widget-button');
        var msg = document.getElementById('ai-chatbot-widget-button-message');
        var win = document.getElementById('ai-chatbot-widget-window');
        var cls = document.getElementById('ai-chatbot-widget-header-close');
        var messages = document.getElementById('ai-chatbot-widget-messages');
        var form = document.getElementById('ai-chatbot-widget-form');
        var report = document.getElementById('ai-chatbot-widget-report');
        var reportForm = document.getElementById('ai-chatbot-widget-report-form');
        var reportInput = document.getElementById('ai-chatbot-widget-report-input');
        var reportAnswer = document.getElementById('ai-chatbot-widget-report-answer');
        var reportStatus = document.getElementById('ai-chatbot-widget-report-status');

        function closeReport() {
            report.hidden = true;
            messages.hidden = false;
            form.hidden = false;
            reportStatus.hidden = true;
        }

        if (messages && report && form) {
            messages.addEventListener('click', function(e) {
                var button = e.target.closest('.ai-chatbot-report-button');
                if (!button) return;

                var answer = button.closest('.ai-chatbot-bot-message').cloneNode(true);
                answer.querySelector('.ai-chatbot-report-button').remove();
                reportAnswer.textContent = answer.textContent.trim();
                reportInput.value = '';
                messages.hidden = true;
                form.hidden = true;
                report.hidden = false;
                reportStatus.hidden = true;
                reportInput.focus();
            });
        }

        var reportBack = document.getElementById('ai-chatbot-widget-report-back');
        if (reportBack) {
            reportBack.addEventListener('click', closeReport);
        }

        if (reportForm) {
            reportForm.addEventListener('submit', function(e) {
                e.preventDefault();

                let formData = new FormData();
                formData.append('action', 'ai_chatbot_report');
                formData.append('ai_chatbot_nonce', ai_chatbot_widget.nonce);
                formData.append('report', reportInput.value);
                formData.append('response', reportAnswer.textContent);

                fetch(ai_chatbot_widget.ajaxurl, {
                    method: 'POST',
                    body: formData
                })
                .then(res => {
                    reportStatus.textContent = 'Bedankt voor uw melding! Sorry voor het ongemak. De chatbot is nog in ontwikkeling, dus antwoorden kunnen fout zijn. Een developer is op de hoogte gesteld en gaat ernaar kijken.';
                    reportStatus.hidden = false;
                })
            });
        }

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

                let formData = new FormData();
                formData.append('action', 'ai_chatbot_search');
                formData.append('ai_chatbot_nonce', ai_chatbot_widget.nonce);
                formData.append('question', msg);

                fetch(ai_chatbot_widget.ajaxurl, {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(res => {
                    messages.removeChild(loading);

                    if (res.success && res.data && res.data.answer) {
                        var botMsg = document.createElement('div');
                        botMsg.className = 'ai-chatbot-message ai-chatbot-bot-message';
                        botMsg.innerHTML = '<strong>' + ai_chatbot_widget.botName + ':</strong> ' + linkify(document.createTextNode(res.data.answer).textContent);
                        addReportButton(botMsg);
                        messages.appendChild(botMsg);
                    } else if (res.data && res.data.message) {
                        var errMsg = document.createElement('div');
                        errMsg.className = 'ai-chatbot-message ai-chatbot-bot-message';
                        errMsg.innerHTML = '<strong>' + ai_chatbot_widget.botName + ':</strong> ' + res.data.message;
                        addReportButton(errMsg);
                        messages.appendChild(errMsg);
                    } else {
                        var errMsg = document.createElement('div');
                        errMsg.className = 'ai-chatbot-message ai-chatbot-bot-message';
                        errMsg.innerHTML = '<strong>' + ai_chatbot_widget.botName + ':</strong> Geen antwoord gevonden.';
                        addReportButton(errMsg);
                        messages.appendChild(errMsg);
                    }

                    scrollMessagesToBottom();
                })
                .catch(err => {
                    messages.removeChild(loading);
                    var errMsg = document.createElement('div');
                    errMsg.className = 'ai-chatbot-message ai-chatbot-bot-message';
                    errMsg.innerHTML = '<strong>' + ai_chatbot_widget.botName + ':</strong> Serverfout.';
                    addReportButton(errMsg);
                    messages.appendChild(errMsg);
                    scrollMessagesToBottom();
                });
            });
        }
    });

    function linkify(text) {
        const urlRegex = /(https?:\/\/[^\s<>"']+)/g;
        
        return text.replace(urlRegex, (url) => {
            return `<a href="${url}" target="_blank" rel="noopener noreferrer">${url}</a>`;
        });
    }
})();
