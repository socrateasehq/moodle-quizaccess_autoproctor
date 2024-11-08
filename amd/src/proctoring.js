define([], function () {
    /**
     * Generates a hashed version of the provided testAttemptId with the provided clientSecret
     * using HMAC with SHA256 for authentication.
     * @param {string} testAttemptId
     * @param {string} clientSecret
     * @returns {string}
     */
    function getHashTestAttemptId(testAttemptId, clientSecret) {
        const secretWordArray = window.CryptoJS.enc.Utf8.parse(clientSecret);
        const messageWordArray = window.CryptoJS.enc.Utf8.parse(testAttemptId);
        const hash = window.CryptoJS.HmacSHA256(messageWordArray, secretWordArray);
        const base64HashedString = window.CryptoJS.enc.Base64.stringify(hash);
        return base64HashedString;
    }

    /**
     * Generates the credentials object required by AutoProctor.
     * @param {string} clientId
     * @param {string} clientSecret
     * @param {string} testAttemptId
     * @returns {object}
     */
    function getCredentials(clientId, clientSecret, testAttemptId) {
        const hashedTestAttemptId = getHashTestAttemptId(testAttemptId, clientSecret);
        const creds = {
            clientId,
            testAttemptId,
            hashedTestAttemptId,
            domain: "https://dev.autoproctor.co",
            environment: "development",
        };
        return creds;
    }

    const getReportOptions = () => {
        return {
            proctoringSummaryDOMId: "ap-report__proctor",
            proctoringOverviewDOMId: "ap-report__overview",
            sessionRecordingDOMId: "ap-report__session",
            groupReportsIntoTabs: true,
            userDetails: {
                name: "First Last",
                email: "user@gmail.com",
            },
        };
    };

    const getProctoringOptions = () => {
        const proctoringOptions = {
            trackingOptions: {
                audio: true,
                numHumans: true,
                tabSwitch: true,
                photosAtRandom: false,
                detectMultipleScreens: true,
                forceFullScreen: false,
                auxiliaryDevice: false,
                recordSession: true,
            },
            showHowToVideo: false,
        };
        return proctoringOptions;
    };

    /**
     * Initializes the AutoProctor instance and sets up event listeners for start and stop buttons.
     * @async
     * @function
     * @name initAutoProctor
     * @param {string} clientId
     * @param {string} clientSecret
     * @param {string} testAttemptId
     * @returns {Promise<void>}
     */
    async function initAutoProctor(clientId, clientSecret, testAttemptId) {
        if (typeof window.AutoProctor === "undefined") {
            setTimeout(() => initAutoProctor(clientId, clientSecret, testAttemptId), 1000);
            return;
        }

        const credentials = getCredentials(clientId, clientSecret, testAttemptId);
        const apInstance = new window.AutoProctor(credentials);
        await apInstance.setup(getProctoringOptions());

        // Auto-start proctoring
        await apInstance.start();

        // Handle quiz submission
        document.querySelector('form#responseform').addEventListener('submit', async (e) => {
            e.preventDefault();
            await apInstance.stop();
            e.target.submit();
        });

        window.addEventListener("apMonitoringStopped", async () => {
            const reportOptions = getReportOptions();
            setTimeout(() => {
                apInstance.showReport(reportOptions);
            }, 10000);
        });
    }

    /**
     * Loads the report for the given test attempt ID.
     * @param {string} clientId
     * @param {string} clientSecret
     * @param {string} testAttemptId
     * @returns {void}
     */
    function loadReport(clientId, clientSecret, testAttemptId) {
        const credentials = getCredentials(clientId, clientSecret, testAttemptId);
        const apInstance = new window.AutoProctor(credentials);
        apInstance.showReport(getReportOptions());
    }

    return {
        init: initAutoProctor,
        loadReport: loadReport,
    };
});
