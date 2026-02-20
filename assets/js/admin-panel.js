jQuery(document).ready(function($) 
{
    const dropzone = document.getElementById("dropzone");
    const fileInput = document.getElementById("fileInput");
    const fileTableBody = document.getElementById("fileTableBody");
    const resultsList = document.getElementById("resultsList");

    dropzone.addEventListener("dragover", function (e) {
        e.preventDefault();
        dropzone.classList.add("drag-over");
    });

    dropzone.addEventListener("dragleave", function () {
        dropzone.classList.remove("drag-over");
    });

    dropzone.addEventListener("drop", function (e) {
        e.preventDefault();
        dropzone.classList.remove("drag-over");
        if (e.dataTransfer.files.length > 0) 
            addFiles(e.dataTransfer.files);
    });

    function randomId() {
      return Math.random().toString(36).slice(2, 10);
    }

    let files = [];
    let running = false;

    fileInput.addEventListener("change", function () {
        if (fileInput.files.length > 0) 
            addFiles(fileInput.files);
        fileInput.value = "";
    });

    function addFiles(fileList)
    {
        if (fileList.length == 0)
            return;
        
        let html = "";
        for (let i = 0; i < fileList.length; i++)
        {
            let f = fileList[i];
            let data = {
                id: randomId(),
                file: f,
                name: f.name,
                size: f.size,
                type: f.type,
                status: "pending",
                reason: null,
            };  
            html += getResultItem(data);
            files.push(data);
        }

        resultsList.innerHTML = resultsList.innerHTML + html;
        
        if (!running)
            aiChatbotUploadFile(files);
    }

    function formatSize(bytes) {
        if (bytes < 1024) return bytes + " B";
        if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(1) + " KB";
        return (bytes / (1024 * 1024)).toFixed(1) + " MB";
    }

    function renderFile(fileName, data) {
        fileTableBody.innerHTML = getFileElement(fileName, data) + fileTableBody.innerHTML;
    }

    function aiChatbotUploadFile() 
    {
        if (files.length == 0)
            return;
        
        running = true;
        let data = files.shift();

        data.status = "processing";

        const resultItem = document.getElementById('ba-chatbot-result-' + data.id);
        if (resultItem) resultItem.replaceWith(getResultItemElement(data));

        const formData = new FormData();
        formData.append('action', 'ai_chatbot_upload_file');
        formData.append('ai_chatbot_file', data.file);
        formData.append('ai_chatbot_nonce', AIChatbot.nonce);

        fetch(ajaxurl, {
            method: 'POST',
            body: formData
        })
        .then(res => res.json())
        .then(res => {
            if (res.success) {
                data.status = "success";

                replaceResult(data);
                renderFile(res.data.file_name, data);
            } else { 
                console.error('Upload error:', res.data.message);

                data.status = "error";
                data.reason = res.data.message;

                replaceResult(data);
            }
            
            if (files.length == 0)
            {
                running = false;
                return;
            }

            aiChatbotUploadFile();
        })
        .catch(err => {
            console.error('AJAX request failed:', err);

            data.status = "error";
            data.reason = "AJAX error: " + err.message;

            replaceResult(data);

            aiChatbotUploadFile();
        });
    }

    function replaceResult(data)
    {
        const resultItem = document.getElementById('ba-chatbot-result-' + data.id);
        if (resultItem) 
        {
            let element = getResultItemElement(data);
            resultItem.replaceWith(element);

            if (data.status == 'success')
            {
                setTimeout(() => {
                
                    element.style.transition = 'opacity 0.5s';
                    element.style.opacity = 0;

                    if (element) {
                        setTimeout(() => {
                            if (element) {
                                element.remove();
                            }
                        }, 500);
                    }
                }, 5000);
            }
        }
    }

    function getFileElement(fileName, data)
    {
        let badgeClass = "badge-success";
        let badgeText = "Uploaded";

        return '<tr id="ba-chatbot-element-' + data.id + '"><td><div class="ba-chatbot-file-name-cell">'
        + '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z"/></svg>'
        + '<span>' + fileName + '</span>'
        + '</div></td>'
        + '<td class="ba-chatbot-col-size">' + formatSize(data.size) + '</td>'
        + '<td class="ba-chatbot-col-status"><span class="ba-chatbot-' + badgeClass + '">' + badgeText + '</span></td>'
        + '<td class="ba-chatbot-col-actions"><button class="ba-chatbot-remove-btn" onclick="removeFile(\'' + data.id + '\')" aria-label="Remove ' + data.name + '">'
        + '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0"/></svg>'
        + '</button></td>'
        + '</tr>';
    }

    function getResultItemElement(data)
    {
        const element = document.createElement('div');
        element.innerHTML = getResultItem(data);
        return element.children[0];
    }

    function getResultItem(data)
    {
        let cls = '';
        let icon = '';

        switch(data.status) {
            case 'success':
                cls = 'ba-chatbot-result-success';
                icon = `<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>`;
                break;
            case 'error':
                cls = 'ba-chatbot-result-fail';
                icon = `<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9.75 9.75l4.5 4.5m0-4.5l-4.5 4.5M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>`;
                break;
            case 'warning':
                cls = 'ba-chatbot-result-warning';
                icon = `<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>`;
                break;
            case 'pending':
                cls = 'ba-chatbot-result-pending';
                icon = '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"><path fill="currentColor" d="M13.463 20.538Q12 19.075 12 17t1.463-3.537T17 12t3.538 1.463T22 17t-1.463 3.538T17 22t-3.537-1.463m5.212-1.162l.7-.7L17.5 16.8V14h-1v3.2zM5 21q-.825 0-1.412-.587T3 19V5q0-.825.588-1.412T5 3h4.175q.275-.875 1.075-1.437T12 1q1 0 1.788.563T14.85 3H19q.825 0 1.413.588T21 5v6.25q-.45-.325-.95-.55T19 10.3V5h-2v3H7V5H5v14h5.3q.175.55.4 1.05t.55.95zm7.713-16.288Q13 4.425 13 4t-.288-.712T12 3t-.712.288T11 4t.288.713T12 5t.713-.288"/></svg>';
                break;
            case 'processing':
                cls = 'ba-chatbot-result-processing';
                icon = `<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"><path fill="none" stroke="currentColor" stroke-linecap="round" stroke-width="2" d="M12 6.99998C9.1747 6.99987 6.99997 9.24998 7 12C7.00003 14.55 9.02119 17 12 17C14.7712 17 17 14.75 17 12"><animateTransform attributeName="transform" attributeType="XML" dur="560ms" from="0,12,12" repeatCount="indefinite" to="360,12,12" type="rotate"/></path></svg>`;
                break;
            default:
                cls = 'ba-chatbot-result-pending';
                icon = ''; // fallback
        }
        
        let result = '<div id="ba-chatbot-result-' + data.id + '" class="ba-chatbot-result-item ' + cls + '">'
        + icon
        + '<div class="ba-chatbot-result-content">'
            + '<div class="ba-chatbot-name">' + data.name + '</div>'
            + (data.reason ? '<div class="ba-chatbot-reason">' + data.reason + '</div>' : '')
        + '</div>';

        if (data.status == 'warning' || data.status == 'error')
        {
            result += 
            '<button class="ba-chatbot-delete" data-id="' + data.id + '" onclick="deleteItemElement(\'' + data.id + '\')" title="Delete">' + 
                '&#10005;' + 
            '</button>';
        }

        return result + '</div>';
    }

    const qdrantUrl = document.getElementById("ba_qdrant_url");
    const qdrantApi = document.getElementById("ba_qdrant_api_key");
    const gptApi = document.getElementById("ba_chatgpt_api_key");

    const qdrantCollection = document.getElementById("ba_chatbot_qdrant_collection").querySelector("input");
    const botName = document.getElementById("ba_chatbot_bot_name").querySelector("input");
    const introMessage = document.getElementById("ba_chatbot_intro_message").querySelector("textarea");
    const openWidget = document.getElementById("ba_chatbot_open_widget").querySelector("input");
    const widgetColor = document.getElementById("ba_chatbot_widget_color").querySelector("input");

    const speechFriendly = document.getElementById("ba_speech_friendly");
    const speechRespectful = document.getElementById("ba_speech_respectful");

    const iconUpload = document.getElementById("ba_chatbot_icon_upload")

    const icon = document.getElementById("ba_bot_icon_current");
    const iconArrow = document.getElementById("ba_bot_icon_arrow");
    const iconPreview = document.getElementById("ba_bot_icon_preview");

    const iconInput = iconUpload.querySelector("input");

    const saveBtn = document.getElementById("ba_chatbot_save_btn");
    const saveIndicator = document.getElementById("ba_chatbot_save_indicator");

    iconInput.addEventListener('change', function() {
        const file = this.files[0];
        if (!file || !file.type.startsWith('image/')) return;

        const reader = new FileReader();
        reader.onload = function(e) {
            let img = iconPreview.querySelector('img');
            if (img)
            {
                img.src = e.target.result;
            }
            else
            {
                icon.innerHTML = "<img src='" + e.target.result + "' alt='Chat Icon' />"
            }
        };
        reader.readAsDataURL(file);

        if (iconArrow)
            iconArrow.style.display = "flex";

        if (iconPreview)
            iconPreview.style.display = "block";
    });

    function setError(element)
    {
        element.classList.add("bg-red-1");
    }

    function removeError(element)
    {
        element.classList.remove("bg-red-1");
    }

    saveBtn.addEventListener('click', function() {
        const formData = new FormData();
        formData.append('action', 'ai_chatbot_save_settings');
        formData.append('ai_chatbot_nonce', AIChatbot.nonce);

        showNotification("loading");

        if (qdrantUrl && qdrantUrl.value)   formData.append('qdrant_url',           qdrantUrl.value);
        if (qdrantApi && qdrantApi.value)   formData.append('qdrant_api',           qdrantApi.value);
        if (gptApi && gptApi.value)         formData.append('gpt_api',              gptApi.value);
        if (qdrantCollection)               formData.append('qdrant_collection',    qdrantCollection.value);
        if (botName)                        formData.append('bot_name',             botName.value);
        if (introMessage)                   formData.append('intro_message',        introMessage.value);
        if (speechFriendly?.checked)        formData.append('speech_friendly',      "friendly");
        if (speechRespectful?.checked)      formData.append('speech_respectful',    "respectful");
        if (openWidget)                     formData.append('open_chat',            openWidget.checked ? "1" : "0");

        if (qdrantUrl)  removeError(qdrantUrl.parentElement)
        if (qdrantApi)  removeError(qdrantApi.parentElement)
        if (gptApi)     removeError(gptApi.parentElement)

        if (widgetColor)
        {
            const hex = widgetColor.value;
            if (/^#[0-9A-F]{6}$/i.test(hex)) {
                formData.append('chat_color', hex);
            } else {
                console.warn('Invalid color value');
            }
        }

        if (iconInput)
        {
            const file = iconInput.files[0];
            if (file) 
            {
                if (!file.type.startsWith('image/')) 
                {
                    alert('Please select a valid image.');
                }
                else 
                {
                    formData.append('bot_icon', file);
                }
            }      
        }

        fetch(ajaxurl, {
            method: 'POST',
            body: formData
        })
        .then(res => res.json())
        .then(res => {
        if (res.success) {
            let img = icon.querySelector('img');
            if (img)
            {
                img.src = res.data.image_url + "?t=" + new Date().getTime();
            }
            else
            {
                icon.innerHTML = "<img src='" + res.data.image_url + "?t=" + new Date().getTime() + "' alt='Chat Icon' />"
            }

            if (iconArrow) iconArrow.style.display = "none";
            if (iconPreview) iconPreview.style.display = "none";

            let apiError = false;

            if (res.data.qdrant_url && res.data.qdrant_url.update && !res.data.qdrant_url.success)
            {
                apiError = true;
                setError(qdrantUrl.parentElement)
                console.warn(res.data.qdrant_url.message);
            }

            if (res.data.qdrant_api && res.data.qdrant_api.update && !res.data.qdrant_api.success)
            {
                apiError = true;
                setError(qdrantApi.parentElement)
                console.warn(res.data.qdrant_api.message);
            }

            if (res.data.gpt_api && res.data.gpt_api.update && !res.data.gpt_api.success)
            {
                apiError = true;
                setError(gptApi.parentElement)
                console.warn(res.data.gpt_api.message);
            }

            if (apiError)
            {
                showNotification('warning');
            }
            else
            {
                showNotification('success');
            }
        } else { 
            console.alert("failed to save");
            showNotification("fail");
        }
    })
    .catch(err => {
        console.error('AJAX request failed:', err);
    });
    });
});

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

function deleteItemElement(id)
{
    let element = document.getElementById('ba-chatbot-result-' + id);
    if (element)
    {
        element.style.transition = 'opacity 0.5s';
        element.style.opacity = 0;

        if (element) {
            setTimeout(() => {
                if (element) {
                    element.remove();
                }
            }, 500);
        }
    }
}

function removeFile(id)
{
    let element = document.getElementById('ba-chatbot-element-' + id);
    if (!element)
    {
        console.error('No element found for file ' + id);
        return;
    }

    let td = element.querySelector('td.ba-chatbot-col-status');
    if (td)
    {
        let s = td.querySelector('span');
        if (s)
        {
            s.classList.remove('ba-chatbot-badge-success');
            s.classList.add('ba-chatbot-badge-deleting');
            s.textContent = "Deleting";
        }
    }

    let span = element.querySelector('span');
    if (!span)
    {
        console.error('No name found for file ' + id);
        return;
    }

    let fileName = encodeURIComponent(span.textContent);

    const formData = new FormData();
    formData.append('action', 'ai_chatbot_file_deletion');
    formData.append('ai_chatbot_delete_file', fileName);
    formData.append('ai_chatbot_nonce', AIChatbot.nonce);

    fetch(ajaxurl, {
        method: 'POST',
        body: formData
    })
    .then(res => res.json())
    .then(res => {
        if (res.success) {
            element.remove();
        } else { 
            console.error('Deletion error:', res.data.message);
            if (td)
            {
                let s = td.querySelector('span');
                if (s)
                {   
                    s.classList.remove('ba-chatbot-badge-deleting');
                    s.classList.add('ba-chatbot-badge-failed'); 
                    s.textContent = "Fail";
                }
            }
        }
    })
    .catch(err => {
        console.error('AJAX request failed:', err);
    });
}