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
    
    const totalMsgs = document.getElementById("ba_total_messages");
    const downloadBtn = document.getElementById("bac_download");
    const downloadDay = document.getElementById("download_day");
    const ctx = document.getElementById('ba-chatbot-weekly-chart').getContext('2d');
    const weeklyChart = new Chart(ctx, {
        type: 'bar',
        data: {
            labels: AIChatbot.chart_labels,
            datasets: [{
                label: 'Messages',
                data: AIChatbot.chart_data,
                backgroundColor: '#2271b1'
            }]
        },
        options: {
            responsive: true,
            plugins: { legend: { display: false } },
            scales: {
                y: { beginAtZero: true, ticks: { stepSize: 1 } },
                x: { grid: { display: false } }
            }
        }
    });

    totalMsgs.innerText = AIChatbot.total_messages;

    downloadBtn.addEventListener('click', () => {
        const day = downloadDay.value;
        const url = `${AIChatbot.ajaxurl}?action=ai_chatbot_download_conversations&download_chatbot_day=${day}&ai_chatbot_nonce=${AIChatbot.nonce}`;
        window.open(url, '_blank');
    });

    async function fetchData() {
        try {
            let formData = new FormData();
            formData.append('action', 'ba_chatbot_get_analytics');
            formData.append('ai_chatbot_nonce', AIChatbot.nonce);
            const res = await fetch(AIChatbot.ajaxurl, {
                method: 'POST',
                body: formData
            })
            const data = await res.json();

            totalMsgs.innerText = data.data.total_messages;
            
            weeklyChart.data.labels = data.data.chart_labels;
            weeklyChart.data.datasets[0].data = data.data.chart_data;
            weeklyChart.update('none');

        } catch (err) {
            console.error('Polling error:', err);
        }

        setTimeout(fetchData, 3000);
    }

    fetchData();
});