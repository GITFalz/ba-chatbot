jQuery(document).ready(function($) 
{
    // == Theme toggle ==
    const btn = document.getElementById("themeToggle")

    btn.onclick = () => {
        document.body.classList.toggle("light-theme")
        localStorage.setItem("ba_theme", document.body.classList.contains("light-theme") ? "light" : "dark")
    }

    const saved = localStorage.getItem("ba_theme")
    btn.checked = saved !== "light";
    // == End ==

    const chatbotMain = document.getElementById("ba_chatbot_main");
    const saveBtn = document.getElementById("ba_chatbot_save_btn");
    const addBtn = document.getElementById("ba_add_language");

    saveBtn.addEventListener('click', () => saveLanguages());

    addBtn.addEventListener('click', () => {
        let element = getLanguageElement();
        addBtn.parentElement.insertBefore(element, addBtn);
        toggleLanguageSection(element)
    })

    function getLanguageElement() {
        let languageData = getLanguageData();
        let name = getUniqueLanguageCode(languageData);
        let html = `
        <div class="ba-chatbot-language ba-chatbot-card rounded-xl b-2 w-full">
            <div class="flew w-full p-0">
                <div class="ba-chatbot-card-header flex items-center just-between gap-4 ba-collapsible-content-header" onclick="toggleLanguageSection(this)">
                    <div class="flex items-center gap-4">
                        <svg class="text-blue-4" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"><path fill="currentColor" d="M8.125 21.213q-1.825-.788-3.187-2.15t-2.15-3.188T2 11.988t.788-3.875t2.15-3.175t3.187-2.15T12.013 2t3.875.788t3.175 2.15t2.15 3.175t.787 3.875t-.787 3.887t-2.15 3.188t-3.175 2.15t-3.875.787t-3.888-.787M12 19.95q.65-.9 1.125-1.875T13.9 16h-3.8q.3 1.1.775 2.075T12 19.95m-2.6-.4q-.45-.825-.787-1.713T8.05 16H5.1q.725 1.25 1.813 2.175T9.4 19.55m5.2 0q1.4-.45 2.488-1.375T18.9 16h-2.95q-.225.95-.562 1.838T14.6 19.55M4.25 14h3.4q-.075-.5-.112-.987T7.5 12t.038-1.012T7.65 10h-3.4q-.125.5-.187.988T4 12t.063 1.013t.187.987m5.4 0h4.7q.075-.5.113-.987T14.5 12t-.038-1.012T14.35 10h-4.7q-.075.5-.112.988T9.5 12t.038 1.013t.112.987m6.7 0h3.4q.125-.5.188-.987T20 12t-.062-1.012T19.75 10h-3.4q.075.5.113.988T16.5 12t-.038 1.013t-.112.987m-.4-6h2.95q-.725-1.25-1.812-2.175T14.6 4.45q.45.825.788 1.713T15.95 8M10.1 8h3.8q-.3-1.1-.775-2.075T12 4.05q-.65.9-1.125 1.875T10.1 8m-5 0h2.95q.225-.95.563-1.838T9.4 4.45Q8 4.9 6.912 5.825T5.1 8"/></svg>
                        <input type="text" class="ba-chatbot-input ba-h2" placeholder="Language name" value="` + name + `">
                        <h2>` + name + `</h2>
                    </div>
                    <button class="b-0 bg-hidden" onclick="deleteLanguage(this)">
                        <svg class="text-red-4" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"><path fill="currentColor" d="M16 9v10H8V9zm-1.5-6h-5l-1 1H5v2h14V4h-3.5zM18 7H6v12c0 1.1.9 2 2 2h8c1.1 0 2-.9 2-2z"/></svg>
                    </button>
                </div>
                <div class="flex row h-full ba-collapsible-content">
                    <div class="flex col w-full">
                        <div class="ba-panel gap-2" id="ba_api_settings">
                            <div class="ba-card flex col gap-2 ba-user-label">
                                <label>
                                    User Label
                                    <input type="text" class="ba-chatbot-input" placeholder="E.g. 'You', 'U', 'Je'">
                                </label>
                            </div>

                            <div class="ba-card flex col gap-2 ba-bot-intro">
                                <label>
                                    Intro Message
                                    <textarea class="ba-chatbot-input" rows="4" placeholder="E.g. 'Hello! how can i help you?'"></textarea>
                                </label>   
                            </div>
                        </div>
                    </div>
                </div>           
            </div>  
        </div>
        `;

        let element = document.createElement('div');
        element.innerHTML = html;
        return element.children[0];
    }
});

function saveLanguages()
{
    showNotification("loading");

    let languageData = getLanguageData();

    const formData = new FormData();
    formData.append('action', 'ba_chatbot_save_languages');
    formData.append('ai_chatbot_nonce', AIChatbot.nonce);
    formData.append('languages', JSON.stringify(languageData));

    fetch(ajaxurl, {
        method: 'POST',
        body: formData
    })
    .then(res => res.json())
    .then(res => {
        if (res.success) {
            showNotification('success');
        } else { 
            console.alert("failed to save");
            showNotification("fail");
        }
    })
    .catch(err => {
        console.error('AJAX request failed:', err);
    });
}

function deleteLanguage(element)
{
    let languageElement = element.closest(".ba-chatbot-language");
    languageElement.remove();
}

function getInputValue(element, name, replacement)
{
    let childElement = element.querySelector(name);
    if (childElement)
    {
        let input = childElement.querySelector("input");
        if (input && input.value !== "")
        {
            return input.value;
        }
    }
    return replacement;
}

function getTextareaValue(element, name, replacement)
{
    let childElement = element.querySelector(name);
    if (childElement)
    {
        let input = childElement.querySelector("textarea");
        if (input && input.value !== "")
        {
            return input.value;
        }
    }
    return replacement;
}

function getUniqueLanguageCode(languages, base = 'lang') {
    let i = 1;
    let code = base;

    while (languages.hasOwnProperty(code)) {
        code = base + i;
        i++;
    }

    return code;
}

function getLanguageData()
{
    const chatbotMain = document.getElementById("ba_chatbot_main");
    let languageData = {};
    let languages = chatbotMain.querySelectorAll(".ba-chatbot-language")
    for (let i = 0; i < languages.length; i++)
    {
        let languageElement = languages[i];
        let name = getInputValue(languageElement, ".ba-chatbot-card-header", getUniqueLanguageCode(languageData))
        let botName = getInputValue(languageElement, ".ba-bot-name", "");
        let userLabel = getInputValue(languageElement, ".ba-user-label", "");
        let botIntro = getTextareaValue(languageElement, ".ba-bot-intro", "");$
        let placeholderText = getInputValue(languageElement, ".ba-placeholder", "");
        languageData[name] = { name: botName, label: userLabel, intro: botIntro, placeholder: placeholderText }
    }
    return languageData;
}

function toggleLanguageSection(element)
{
    const chatbotMain = document.getElementById("ba_chatbot_main");
    let languages = chatbotMain.querySelectorAll(".ba-chatbot-language")
    let languageElement = element.closest(".ba-chatbot-language");
    for (let i = 0; i < languages.length; i++)
    {
        let language = languages[i];
        if (language == languageElement)
        {
            let header = language.querySelector(".ba-collapsible-content-header");
            if (header) header.classList.add("expanded");

            let contents = language.querySelectorAll(".ba-collapsible-content");
            contents.forEach((content) => { if (content) content.classList.remove("collapsed"); });
        }
        else
        {
            let header = language.querySelector(".ba-collapsible-content-header");
            if (header)
            {
                header.classList.remove("expanded");
                let input = header.querySelector("input");
                let h = header.querySelector("h2");
                if (input && h)
                    h.innerText = input.value;
            }

            let contents = language.querySelectorAll(".ba-collapsible-content");
            contents.forEach((content) => { if (content) content.classList.add("collapsed"); });
        }
    }
}

function showNotification(type) {
    const notifications = document.querySelectorAll('.notification');
    notifications.forEach(n => n.style.display = 'none');

    const el = document.querySelector(`.${type}`);
    if (el) {
        el.style.display = 'inline-flex';
        el.classList.remove('fadeOut');
        void el.offsetWidth;
        el.classList.add('fadeOut');
    }
}