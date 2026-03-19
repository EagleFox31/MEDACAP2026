<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Chatbox</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
    body {
        font-family: 'Inter', sans-serif;
    }

    .custom-chat-widget {
        position: fixed;
        bottom: 20px;
        right: 20px;
        width: 300px;
        height: 50px;
        /* Initial height */
        border-radius: 12px;
        background: #ffffff;
        box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
        display: flex;
        flex-direction: column;
        transition: height 0.3s ease;
        overflow: hidden;
        z-index: 1000;
        /* Ensure it appears above other elements */
    }

    .custom-chat-widget.expanded {
        height: 350px;
        /* Height when expanded */
    }

    .custom-chat-header {
        background: #007bff;
        color: white;
        padding: 12px;
        text-align: center;
        border-bottom: 1px solid #0056b3;
        cursor: pointer;
        font-size: 14px;
    }

    .custom-chat-content {
        flex: 1;
        padding: 12px;
        overflow-y: auto;
        display: block;
        /* Always displayed in expanded state */
        font-size: 14px;
        line-height: 1.5;
    }

    .custom-chat-message {
        border-radius: 6px;
        padding: 8px;
        margin-bottom: 8px;
        max-width: 70%;
        word-wrap: break-word;
        font-size: 14px;
    }

    .custom-chat-message.bot {
        background: #e1e1e1;
        color: #333;
        margin-left: 0;
    }

    .custom-chat-message.user {
        background: #007bff;
        color: #ffffff;
        text-align: right;
        margin-left: auto;
    }

    .custom-chat-input {
        display: flex;
        align-items: center;
        padding: 12px;
        border-top: 1px solid #ddd;
        background: #f2f2f2;
    }

    .custom-chat-input input {
        flex: 1;
        border: 1px solid #ddd;
        border-radius: 4px;
        padding: 8px;
        margin-right: 10px;
        font-size: 14px;
    }

    .custom-chat-input button {
        background: #007bff;
        color: white;
        border: none;
        border-radius: 4px;
        padding: 8px 16px;
        cursor: pointer;
        font-size: 14px;
    }

    .custom-chat-input button:hover {
        background: #0056b3;
    }

    .custom-chat-header i {
        color: white;
        margin-right: 8px;
    }

    .custom-chat-input button i {
        color: white;
        /* Set the icon color to white */
        font-size: 16px;
        /* Size of the icon */
    }

    .typing-indicator {
        display: none;
        color: #999;
        font-style: italic;
        font-size: 12px;
    }

    /* Remove the light blue button color and update the button styles */
    .chat-button-container {
        display: flex;
        gap: 10px;
        /* Space between buttons */
        margin-top: 10px;
        justify-content: center;
        /* Center buttons horizontally */
    }

    .chat-button {
        background: #333;
        /* New background color */
        border: none;
        border-radius: 4px;
        /* Small border radius for rectangular look */
        padding: 4px 8px;
        /* Reduced padding for smaller size */
        color: #fff;
        /* White text color */
        cursor: pointer;
        font-size: 10px;
        /* Reducing font size */
        text-align: center;
        transition: background 0.3s, color 0.3s;
    }

    .chat-button:hover {
        background: #007bff;
        /* Blue background on hover */
        color: #fff;
        /* Ensure text remains white on hover */
    }

    .chat-button:focus {
        outline: none;
    }

    .custom-chat-input {
        position: relative;
        display: flex;
        align-items: center;
    }

    .input-container {
        position: relative;
        width: 100%;
    }

    #custom-user-input {
        width: 100%;
        padding-right: 80px;
        /* Adjust as needed to fit the counter */
    }

    #char-count {
        position: absolute;
        right: 5px;
        /* Adjust to your liking */
        top: 50%;
        transform: translateY(-50%);
        font-size: 12px;
        /* Adjust as needed */
        color: #888;
        /* Adjust color as needed */
    }
    </style>

</head>

<body>
    <div class="custom-chat-widget" id="custom-chat-widget">
        <div class="custom-chat-header" id="custom-chat-header">
            <i class="fas fa-chevron-up"></i> Des préoccupations? Cliquez ici.
        </div>
        <div class="custom-chat-content" id="custom-chat-content">
            <!-- Initial message will be dynamically loaded -->
        </div>
        <div class="chat-button-container" style="background-color: #333;">
            <button id="aide-button" class="chat-button">Aide</button>
            <button id="probleme-button" class="chat-button">Signaler un problème</button>
            <!-- Removed the "Autre" button -->
        </div>
        <div class="custom-chat-input" id="chat-input-container" style="display: none;">
            <div class="input-container">
                <input type="text" id="custom-user-input" placeholder="Ecrire ici..." maxlength="100">
                <span id="char-count">0/100</span>
            </div>
            <button onclick="customSendMessage()" id="send-button">
                <i class="fas fa-paper-plane"></i>
            </button>
        </div>
        <div class="typing-indicator" id="typing-indicator">CFAO Mobility Academy écrit...</div>
    </div>

    <script>
    (function() {
        const chatHeader = document.getElementById('custom-chat-header');
        const chatWidget = document.getElementById('custom-chat-widget');
        const chatContent = document.getElementById('custom-chat-content');
        const input = document.getElementById('custom-user-input');
        const typingIndicator = document.getElementById('typing-indicator');
        const sendButton = document.getElementById('send-button');
        const chatInputContainer = document.getElementById('chat-input-container');
        let isFirstOpen = true;
        let problemMode = false;

        // Utility functions to manage cookies
        function setCookie(name, value, days) {
            let expires = "";
            if (days) {
                const date = new Date();
                date.setTime(date.getTime() + (days * 24 * 60 * 60 * 1000));
                expires = "; expires=" + date.toUTCString();
            }
            document.cookie = name + "=" + (value || "") + expires + "; path=/";
        }

        function getCookie(name) {
            const nameEQ = name + "=";
            const ca = document.cookie.split(';');
            for (let i = 0; i < ca.length; i++) {
                let c = ca[i].trim();
                if (c.indexOf(nameEQ) === 0) return c.substring(nameEQ.length, c.length);
            }
            return null;
        }

        function clearCookie(name) {
            document.cookie = name + '=; Max-Age=-99999999; path=/';
        }

        function clearCookies() {
            clearCookie('userFullName');
            clearCookie('userObjectId');
            clearCookie('chatExpanded');
        }

        function initializeCookies() {
            let userFullName = getCookie('userFullName');
            let userObjectId = getCookie('userObjectId');

            if (!userFullName) {
                userFullName = '<?php echo $_SESSION["firstName"] . " " . $_SESSION["lastName"]; ?>';
                setCookie('userFullName', userFullName, 1);
            }

            if (!userObjectId) {
                userObjectId = '<?php echo $_SESSION["id"]; ?>';
                setCookie('userObjectId', userObjectId, 1);
            }
        }

        function handleUserLogin() {
            clearCookies();
            initializeCookies();
        }

        handleUserLogin();

        setCookie('chatExpanded', false, 1);
        chatHeader.innerHTML = '<i class="fas fa-chevron-up"></i> Des préoccupations? Cliquez ici.';

        chatHeader.addEventListener('click', () => {
            if (!chatWidget.classList.contains('expanded')) {
                chatWidget.classList.add('expanded');
                setCookie('chatExpanded', true, 1);

                chatHeader.innerHTML = '<i class="fas fa-chevron-down"></i> Cliquez ici pour abaisser';

                if (isFirstOpen) {
                    showTypingIndicator();
                    setTimeout(() => {
                        hideTypingIndicator();
                        displayInitialBotMessages();
                        isFirstOpen = false;
                    }, 2000);
                }

                loadMessages();
            } else {
                chatWidget.classList.remove('expanded');
                setCookie('chatExpanded', false, 1);
                chatHeader.innerHTML = '<i class="fas fa-chevron-up"></i> Des préoccupations? Cliquez ici.';
            }
        });

        function showTypingIndicator() {
            typingIndicator.style.display = 'block';
        }

        function hideTypingIndicator() {
            typingIndicator.style.display = 'none';
        }

        function displayInitialBotMessages() {
            const initialMessages = [
                `Salutations <strong>${getCookie('userFullName')}</strong> ! Bienvenue sur l'espace <strong>Aide</strong> et <strong>Commentaires</strong> de l'application <strong>MEDACAP</strong>, en quoi puis-je vous aider?`,
                "Sélectionnez l'une des options ci-dessous."
            ];
            updateChatContent(initialMessages, 'CFAO Mobility Academy');
        }

        function updateChatContent(messages, userName) {
            messages.forEach(message => {
                const div = document.createElement('div');
                div.className =
                    `custom-chat-message ${userName === 'CFAO Mobility Academy' ? 'bot' : 'user'}`;
                div.innerHTML = `<strong>${userName}:</strong> ${message}`;
                chatContent.appendChild(div);
            });
            chatContent.scrollTop = chatContent.scrollHeight;
        }

        document.getElementById('aide-button').addEventListener('click', () => {
            handleHelpRequest('aide');
        });

        document.getElementById('probleme-button').addEventListener('click', () => {
            handleHelpRequest('problème');
        });

        function handleHelpRequest(type) {
            const userMessage = type === 'aide' ? 'Aide' : 'Signaler un problème';
            updateChatContent([userMessage], getCookie('userFullName'));

            let messages = [];
            if (type === 'aide') {
                messages = [
                    "Cette mesure de compétences consiste à l’identification à travers des questionnaires, de vos connaissances théoriques et de la maitrise de vos tâches professionnelles sur chacun de vos niveaux techniques (Junior, Senior et Expert).",
                    "Bien vouloir lire attentivement chaque question de ces 02 types de questionnaires, sélectionner une des propositions et enregistrer votre choix en cliquant sur le bouton <strong>'Valider'</strong>.",
                    "Pour toute préoccupation, remarque, suggestion ou commentaire sur un test ou sur une question, bien vouloir nous l’indiquer en cliquant sur l’option <strong>« Signaler un problème ».</strong>"
                ];
            } else if (type === 'problème') {
                problemMode = true;
                messages = [
                    "Merci d’avance pour votre commentaire. Bien vouloir décrire votre préoccupation dans le champ ci-dessous.",
                    "Si cela concerne une question, veillez à toujours préciser <strong>l'identifiant de la question (Ex : Fac/Decla24).</strong>"
                ];
                chatInputContainer.style.display = 'flex'; // Show the input area
            }
            showTypingIndicator();
            setTimeout(() => {
                hideTypingIndicator();
                updateChatContent(messages, 'CFAO Mobility Academy');
            }, 2000);
        }

        function getLevelFromUrl() {
            const urlParams = new URLSearchParams(window.location.search);
            return urlParams.get('level') || 'Unknown';
        }

        function customSendMessage() {
            const userInput = input.value.trim();
            if (userInput) {
                updateChatContent([userInput], getCookie('userFullName'));
                input.value = '';

                saveMessage(userInput);

                showTypingIndicator();
                setTimeout(() => {
                    hideTypingIndicator();
                    let response =
                        "Merci pour votre message! Un membre de notre équipe vous répondra sous peu.";

                    if (problemMode) {
                        updateChatContent([
                            "Votre préoccupation a bien été signalée. Elle sera prise en compte et traitée sous peu.",
                            "Autre chose à signaler ?"
                        ], 'CFAO Mobility Academy');
                        problemMode = false;
                        chatInputContainer.style.display = 'none'; // Hide the input area
                    } else {
                        if (/bonjour/i.test(userInput)) {
                            response = "Bonjour! Comment puis-je vous aider aujourd'hui?";
                        } else if (/bonsoir/i.test(userInput)) {
                            response = "Bonsoir! Comment puis-je vous aider ce soir?";
                        } else if (/comment allez-vous\?/i.test(userInput)) {
                            response = "Je vais bien, merci! Comment puis-je vous aider?";
                        } else if (/merci/i.test(userInput)) {
                            response = "Je vous en prie! N'hésitez pas si vous avez d'autres questions.";
                        }

                        updateChatContent([response], 'CFAO Mobility Academy');

                        if (userInput.toLowerCase().includes('autre')) {
                            updateChatContent([
                                "N'hésitez pas à poser d'autres questions ou à exprimer d'autres préoccupations."
                            ], 'CFAO Mobility Academy');
                        }
                    }
                }, 2000);
            }
        }

        function saveMessage(message) {
            const url = window.location.href;
            const truncatedUrl = url.includes('userEvaluation') ? 'QCM Tâches Professionnelles Manager' :
                url.includes('userQuizFactuel') ? 'QCM connaissances' :
                url.includes('userQuizDeclaratif') ? 'QCM tâches professionnelles' :
                url;


            const data = {
                userId: getCookie('userObjectId'),
                userName: getCookie('userFullName'),
                message: message,
                timestamp: new Date().toLocaleString(),
                url: truncatedUrl,
                level: getLevelFromUrl() // Add level to the data
            };

            fetch('chatbox.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify(data),
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        console.log('Message saved:', data);
                    } else {
                        console.error('Save failed:', data.message);
                    }
                })
                .catch((error) => {
                    console.error('Error:', error);
                });
        }

        function loadMessages() {
            fetch(`chatbox.php?getMessages=true`)
                .then(response => response.json())
                .then(data => {
                    console.log('Response data:', data);

                    // Do not display old messages by skipping the updateChatContent call
                    if (data.messages && Array.isArray(data.messages)) {
                        // Commented out to skip loading old messages
                        /*
                        data.messages.forEach(message => {
                            if (message.message) {
                                updateChatContent([message.message], data.userFullName);
                            }
                        });
                        */
                        console.log('Messages successfully loaded');
                    } else {
                        console.error('Invalid message data format');
                    }
                })
                .catch(error => console.error('Error loading messages:', error));
        }

        sendButton.addEventListener('click', customSendMessage);
        input.addEventListener('keypress', (e) => {
            if (e.key === 'Enter') {
                e.preventDefault();
                customSendMessage();
            }
        });

        window.addEventListener('load', () => {
            if (getCookie('chatExpanded') === 'true') {
                chatWidget.classList.add('expanded');
            }
        });
    })();
    </script>


    <script>
    document.addEventListener('DOMContentLoaded', () => {
        const inputField = document.getElementById('custom-user-input');
        const charCount = document.getElementById('char-count');
        const maxLength = 100;

        inputField.addEventListener('input', () => {
            const currentLength = inputField.value.length;
            charCount.textContent = `${currentLength}/${maxLength}`;

            if (currentLength > maxLength) {
                inputField.value = inputField.value.slice(0, maxLength);
                charCount.textContent = `${maxLength}/${maxLength}`;
            }
        });
    });
    </script>


</body>

</html>