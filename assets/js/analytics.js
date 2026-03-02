const downloadBtn = document.getElementById("bac_download");
const downloadDay = document.getElementById("download_day");

downloadBtn.addEventListener('click', () => {
    const day = downloadDay.value;
    const url = `${AIChatbot.ajaxurl}?action=ai_chatbot_download_conversations&download_chatbot_day=${day}&ai_chatbot_nonce=${AIChatbot.nonce}`;
    window.open(url, '_blank');
});