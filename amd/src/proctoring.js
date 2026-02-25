define(["jquery", "core/templates"], function ($, Templates) {
    const IFRAME_NAME = "ap-iframe";
    const MOODLE_PREFLIGHT_FORM_ID = "mod_quiz_preflight_form";
    const MOODLE_PAGE_CONTENT_ID = "page-content";
    const MOODLE_FINISH_FORM_SUBMIT_BTN = "#frm-finishattempt button[type='submit']";
    const MOODLE_FINISH_MODAL_SUBMIT_BTN = "div.modal-footer > button.btn.btn-primary";

    // Cached variables
    let _apInstance;
    let _trackingOptions;
    let _testAttemptId;
    let _lookupKey;

    // Flags
    let isPreflightFormSubmitted = false;
    let isApProgressCompleted = false;
    let proctoringInProgress = false;

    // Cached DOM elements
    let $apIframe;
    let $apIframeLoader;

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
            domain: "https://dev.autoproctor.co", // TODO: Change to production domain before release
            environment: "development",           // TODO: Change to 'production' before release
        };
        return creds;
    }

    /**
     * Generates the report options object required by AutoProctor.
     * @returns {object}
     */
    const getReportOptions = () => {
        return {
            showProctoringOverview: true,
            showProctoringSummary: true,
            showSessionRecording: true,
            proctoringSummaryDOMId: "ap-report__proctor",
            proctoringOverviewDOMId: "ap-report__overview",
            sessionRecordingDOMId: "ap-report__session",
            groupReportsIntoTabs: false,
            showDownloadReportBtn: true,
            userDetails: {
                name: "First Last",
                email: "user@gmail.com",
            },
        };
    };

    /**
     * Generates the proctoring options object required by AutoProctor.
     * @param {object} trackingOptions
     * @returns {object}
     */
    const getProctoringOptions = (trackingOptions) => {
        const proctoringOptions = {
            trackingOptions: trackingOptions ?? {
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
            lookupKey: _lookupKey,
            testContainerId: MOODLE_PAGE_CONTENT_ID,
            alertBeforeUnload: false,
        };
        return proctoringOptions;
    };

    /**
     * Tracks URL changes in an iframe and executes a callback when changes occur
     * @param {string} iframeId - The ID of the iframe to monitor
     * @param {Function} callback - Function to call when URL changes, receives new URL as parameter
     */
    const onIframeUrlChanged = (iframeId, callback) => {
        const $iframe = document.getElementById(iframeId);
        let previousUrl = null;

        // Main function to handle URL changes
        const handleUrlChange = () => {
            const currentUrl = $iframe.contentWindow.location.href;

            // Show loading indicator
            $apIframeLoader.classList.remove("aptw-hidden");

            // Only process if URL has actually changed
            if (currentUrl !== previousUrl) {
                callback(currentUrl);
                previousUrl = currentUrl;
            }
        };

        // Set up iframe load event. handleUrlChange is called both when iframe is loaded
        // as well as when iframe is unloaded. While maintaining focus on the main window.
        $iframe.addEventListener("load", () => {
            handleUrlChange();

            // Add unload listener to iframe content
            $iframe.contentWindow.addEventListener("unload", () => {
                window.focus();
                handleUrlChange();
            });

            hideLoaderIfProgressCompleted();
        });
    };

    /**
     * Hides the loader if the progress is completed
     */
    const hideLoaderIfProgressCompleted = () => {
        if (isApProgressCompleted) {
            $apIframeLoader.remove();
        } else {
            setTimeout(() => {
                hideLoaderIfProgressCompleted();
            }, 500);
        }
    };

    /**
     * Handles URL changes in the iframe and starts or stops the proctoring process accordingly.
     * Proctoring will only start on attempt.php and summary.php
     * And will stop on any other page
     * @param {string} newUrl - The new URL of the iframe
     */
    const handleIframeUrlChange = async (newUrl) => {
        const fileLocation = getUrlFileLocation(newUrl);

        /**
         * If it is summary.php, wait for the finish form to be submitted
         * and then wait for the final submit button to appear in the modal
         * and then stop the AP session on click of final submit button before
         * redirecting to newUrl. Or else if the AP session is not started,
         * just redirect to newUrl
         */
        if (fileLocation === "summary.php") {
            // Handle quiz submission process
            const handleFinalSubmit = ($finalSubmitBtn) => {
                $finalSubmitBtn.addEventListener("click", async () => {
                    if (proctoringInProgress) {
                        await _apInstance?.stop();
                        window.addEventListener("apMonitoringStopped", () => {
                            proctoringInProgress = false;
                            window.location.href = newUrl;
                        });
                    } else {
                        window.location.href = newUrl;
                    }
                });
            };

            // Wait for finish form button and set up submission flow
            waitForElement(
                MOODLE_FINISH_FORM_SUBMIT_BTN,
                ($finishBtn) => {
                    $finishBtn.addEventListener("click", () => {
                        // Wait for final submit button to appear in the modal
                        waitForElement(MOODLE_FINISH_MODAL_SUBMIT_BTN, handleFinalSubmit, true);
                    });
                },
                true
            );
            return;
        }

        // If it is review.php and proctoring is in progress,
        // stop the AP session and redirect to newUrl
        if (fileLocation === "review.php" && proctoringInProgress) {
            await _apInstance?.stop();
            window.addEventListener("apMonitoringStopped", () => {
                proctoringInProgress = false;
                window.location.href = newUrl;
            });
        }

        // Create new AP session on attempt.php
        if (fileLocation === "attempt.php") {
            await createNewApSession(newUrl, _testAttemptId, _trackingOptions);
            return;
        }

        // If it is any other page, redirect to it
        window.location.href = newUrl;
    };

    /**
     * Creates a new AP session and if it fails, it will retry up to 5 times
     * @param {string} url
     * @param {string} testAttemptId
     * @param {object} trackingOptions
     * @param {number} retriesLeft
     */
    const createNewApSession = (url, testAttemptId, trackingOptions, retriesLeft = 5) => {
        try {
            const params = new URL(url).searchParams;
            const attemptId = params.get("attempt");
            const sesskey = M.cfg?.sesskey || document.querySelector('input[name="sesskey"]')?.value;

            fetch(M.cfg.wwwroot + "/mod/quiz/accessrule/autoproctor/create_session.php", {
                method: "POST",
                headers: {
                    "Content-Type": "application/x-www-form-urlencoded",
                },
                body: new URLSearchParams({
                    sesskey: sesskey,
                    attemptid: attemptId,
                    test_attempt_id: testAttemptId,
                    tracking_options: JSON.stringify(trackingOptions),
                }),
            });
        } catch (error) {
            if (retriesLeft > 0) {
                setTimeout(() => createNewApSession(url, testAttemptId, trackingOptions, retriesLeft - 1), 1000);
            } else {
                throw error;
            }
        }
    };

    /**
     * Adds an iframe to the document body with the specified attributes and styles.
     * Caches iframe in window object for later use
     */
    const addIframe = () => {
        const $iframe = document.createElement("iframe");
        $iframe.name = IFRAME_NAME;
        $iframe.id = IFRAME_NAME;
        $iframe.classList.add(
            IFRAME_NAME,
            "aptw-hidden",
            "aptw-absolute",
            "aptw-inset-0",
            "aptw-w-screen",
            "aptw-h-screen"
        );
        document.body.append($iframe);

        // Cache iframe for later use
        $apIframe = $iframe;
    };

    /**
     * Adds a page loader to the document body.
     * Caches loader in window object for later use
     */
    const addPageLoader = async () => {
        const loaderName = "ap-iframe-loader";
        $apIframeLoader = document.createElement("div");
        $apIframeLoader.id = loaderName;

        // IMPORTANT: Z-index must be greater than the z-index of the page content
        // but less than the z-index of Swals shown by AutoProctor
        $apIframeLoader.style.zIndex = 1020;

        $apIframeLoader.classList.add(
            "aptw-fixed",
            "aptw-top-0",
            "aptw-bottom-0",
            "aptw-hidden",
            "aptw-w-screen",
            "aptw-h-screen",
            "aptw-bg-white",
            "aptw-flex",
            "aptw-items-center",
            "aptw-justify-center"
        );

        document.body.append($apIframeLoader);

        try {
            const html = await Templates.render("quizaccess_autoproctor/loader", {});
            if (html) {
                $apIframeLoader.innerHTML = html;
                bindApProgressUpdate();
            }
        } catch (error) {
            // Fallback loader in case template fails
            $apIframeLoader.innerHTML = '<div class="aptw-text-center">Loading...</div>';
        }
    };

    /**
     * Binds the apProgressUpdate event to the loader to update the progress bar
     */
    const bindApProgressUpdate = () => {
        window.addEventListener("apProgressUpdate", (e) => {
            const { action } = e.detail;
            if (action === "hide") {
                isApProgressCompleted = true;
            } else if (action === "show") {
                document.getElementById("ap-progress-text").style.display = "block";
                document.getElementById("pre-loader-text").style.display = "none";
                $apIframeLoader?.classList.remove("aptw-hidden");
            }
            incrementSetupProgBar();
        });

        /**
         * Increments the progress bar by 10% on each call
         */
        function incrementSetupProgBar() {
            const progressBarPercent = document.getElementById("ap-progress-bar-percent");
            const progressBar = document.getElementById("ap-progress-bar");

            if (!progressBarPercent || !progressBar) {
                return;
            }

            const currentVal = parseInt(progressBarPercent.innerText) || 0;
            const newVal = currentVal + 10;

            progressBarPercent.innerText = newVal;
            progressBar.style.width = newVal + "%";
        }
    };

    /**
     * Adds an event listener to the preflight form to intercept the submit event
     * @param {object} $preflightForm
     */
    const addSubmitPreflightEvent = ($preflightForm) => {
        // Set start attempt button to the center
        const formActionsContainer = $preflightForm.getElementsByClassName("felement");
        if (formActionsContainer.length > 0) {
            const buttonContainer =
                formActionsContainer[formActionsContainer.length - 1].getElementsByClassName("aptw-flex");
            if (buttonContainer.length > 0) {
                buttonContainer[0].classList.add("aptw-justify-center");
            }
        }

        // Intercept submit event
        $preflightForm.addEventListener("submit", async (e) => {
            e.preventDefault();

            if (isPreflightFormSubmitted) {
                return;
            }
            isPreflightFormSubmitted = true;

            const formData = $(`#${MOODLE_PREFLIGHT_FORM_ID}`).serialize();
            const autoProctorConsent = formData.includes("autoproctor_consent=1");

            if (autoProctorConsent) {
                // Close preflight form overlay
                const $cancelPreflightAttempt = $preflightForm.querySelector("#id_cancel");
                $cancelPreflightAttempt?.click();

                await _apInstance.setup(getProctoringOptions(_trackingOptions));
                await _apInstance.start();
                window.addEventListener("apMonitoringStarted", () => {
                    proctoringInProgress = true;
                    submitPreflightForm(e.target.action, $preflightForm, formData);
                });
            }
        });
    };

    /**
     * Submits the preflight form and handles the response
     * If the response contains a preflight form, it will submit that form instead
     * Otherwise, it will submit the preflight form as usual
     *
     * It will also show the iframe loader and remove the page loader and handle url changes in the iframe
     * @param {string} submitUrl
     * @param {object} $preflightForm
     * @param {string} formData
     */
    const submitPreflightForm = (submitUrl, $preflightForm, formData) => {
        $.ajax({
            url: submitUrl,
            method: "POST",
            data: formData,
        })
            .done((res) => {
                const parsedPreflightForm = $($.parseHTML(res)).find(`form[id="${MOODLE_PREFLIGHT_FORM_ID}"]`);
                if (parsedPreflightForm.length === 1) {
                    $preflightForm.submit();
                    return;
                }

                $apIframeLoader.classList.remove("aptw-hidden");
                $preflightForm.setAttribute("target", IFRAME_NAME);
                $preflightForm.submit();

                onIframeUrlChanged(IFRAME_NAME, handleIframeUrlChange);

                $apIframe.classList.remove("aptw-hidden");

                document.querySelector("#page-wrapper")?.remove();
            })
            .fail(() => {
                isPreflightFormSubmitted = false;
            });
    };

    /**
     * Gets the file location from a URL
     * Example: host.com/mod/quiz/attempt.php?x=1&y=2 to attempt.php
     * @param {string} url
     * @returns {string}
     */
    const getUrlFileLocation = (url) => {
        const urlObject = new URL(url);
        return urlObject.pathname.split("/").pop();
    };

    /**
     * Waits for an element to appear in the document or iframe (if isInsideIframe is true)
     * and then calls a callback with the element.
     * @param {string} selector - The CSS selector to search for
     * @param {Function} callback - Function to call with the found element as parameter
     * @param {boolean} isInsideIframe - Whether the element is inside the iframe
     */
    const waitForElement = (selector, callback, isInsideIframe = false) => {
        const elementIntervalId = setInterval(() => {
            const targetElement = isInsideIframe
                ? document.getElementById(IFRAME_NAME)?.contentWindow?.document.querySelector(selector)
                : document.querySelector(selector);
            if (targetElement) {
                clearInterval(elementIntervalId);
                callback(targetElement);
            }
        }, 500);
    };

    /**
     * Initializes the AutoProctor instance and sets up event listeners for start and stop buttons.
     * @async
     * @function
     * @name initAutoProctor
     * @param {string} clientId
     * @param {string} clientSecret
     * @param {string} testAttemptId
     * @param {object} trackingOptions
     * @param {number} cmid
     * @param {string} lookupKey
     * @returns {Promise<void>}
     */
    async function initAutoProctor(clientId, clientSecret, testAttemptId, trackingOptions, cmid, lookupKey) {
        // Check if AutoProctor is already loaded and retry if not
        if (typeof window.AutoProctor === "undefined") {
            setTimeout(
                () => initAutoProctor(clientId, clientSecret, testAttemptId, trackingOptions, cmid, lookupKey),
                1000
            );
            return;
        }

        // Cache variables in execution context
        _trackingOptions = trackingOptions ?? {};
        _testAttemptId = testAttemptId ?? "";
        _lookupKey = lookupKey ?? "";

        // Initialize AutoProctor instance
        const credentials = getCredentials(clientId, clientSecret, testAttemptId);
        _apInstance = new window.AutoProctor(credentials);

        addIframe();
        addPageLoader();

        waitForElement(`#${MOODLE_PREFLIGHT_FORM_ID}`, ($targetElement) => {
            addSubmitPreflightEvent($targetElement);

            // Prevent user to directly access attempt.php by overriding url to view.php
            const isOnStartattempt = ["startattempt.php", "attempt.php"].includes(
                getUrlFileLocation(window.location.href)
            );
            if (isOnStartattempt) {
                window.history.replaceState({}, "", `/mod/quiz/view.php?id=${cmid}`);
            }
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
        // Check if AutoProctor is already loaded and retry if not
        if (typeof window.AutoProctor === "undefined") {
            setTimeout(() => loadReport(clientId, clientSecret, testAttemptId), 1000);
            return;
        }

        const credentials = getCredentials(clientId, clientSecret, testAttemptId);
        const apInstance = new window.AutoProctor(credentials);
        apInstance.showReport(getReportOptions());
    }

    /**
     * Adds a "View Proctoring Report" button to the quiz review page.
     * The button opens the report in a modal using the AutoProctor SDK.
     * @param {string} reportUrl - The URL to the report (fallback external link)
     * @param {string} buttonLabel - The label for the button
     * @param {string} clientId - The AutoProctor client ID
     * @param {string} clientSecret - The AutoProctor client secret
     * @param {string} testAttemptId - The test attempt ID for this session
     */
    function addReportButton(reportUrl, buttonLabel, clientId, clientSecret, testAttemptId) {
        // Track if report has been loaded
        let reportLoaded = false;
        let tabsInitialized = false;

        // Tab styles
        const activeTabStyle = "border-bottom: 3px solid #106bbf; color: #106bbf; padding-bottom: 8px;";
        const inactiveTabStyle = "border-bottom: 3px solid transparent; color: #6b7280; padding-bottom: 8px;";

        // Tab switching helper function
        const switchTab = (activeTabId) => {
            const tabs = ["proctoring-summary-tab", "session-recording-tab"];
            const contents = ["ap-report-content", "ap-report__session"];

            tabs.forEach((tabId, index) => {
                const tab = document.getElementById(tabId);
                const content = document.getElementById(contents[index]);

                if (tabId === activeTabId) {
                    tab.style.cssText = activeTabStyle + " cursor: pointer; white-space: nowrap;";
                    content.style.display = "block";
                } else {
                    tab.style.cssText = inactiveTabStyle + " cursor: pointer; white-space: nowrap;";
                    content.style.display = "none";
                }
            });
        };

        // Wait for DOM to be ready
        const insertButton = () => {
            // Find the quiz info table or summary section to insert the button
            const summaryTable = document.querySelector(".quizreviewsummary");
            const attemptInfo = document.querySelector(".mod_quiz-attempt-summary");
            const targetElement = summaryTable || attemptInfo;

            if (!targetElement) {
                // Retry if the target element is not found yet
                setTimeout(insertButton, 500);
                return;
            }

            // Check if button already exists
            if (document.getElementById("ap-view-report-btn")) {
                return;
            }

            // Create the button container
            const buttonContainer = document.createElement("div");
            buttonContainer.style.cssText = "margin: 15px 0; text-align: center;";

            // Create the button
            const button = document.createElement("button");
            button.id = "ap-view-report-btn";
            button.type = "button";
            button.className = "btn btn-primary";
            button.style.cssText = "background-color: #106bbf; border-color: #106bbf;";
            button.innerHTML = `<i class="fa fa-eye mr-2"></i> ${buttonLabel}`;

            // Create report container (hidden by default) with manual tabs
            const reportContainer = document.createElement("div");
            reportContainer.id = "ap-review-report-container";
            reportContainer.style.cssText = "display: none; margin-top: 20px;";
            reportContainer.innerHTML = `
                <div id="ap-report-tabs" style="display: flex; gap: 24px; font-size: 16px; font-weight: 600;
                            border-bottom: 1px solid #e5e7eb; padding: 8px 0; margin-bottom: 16px;">
                    <div id="proctoring-summary-tab"
                         style="${activeTabStyle} cursor: pointer; white-space: nowrap;">
                        Proctoring Summary
                    </div>
                    <div id="session-recording-tab"
                         style="${inactiveTabStyle} cursor: pointer; white-space: nowrap;">
                        Session Recording
                    </div>
                </div>
                <div id="ap-report-loader" style="text-align: center; padding: 40px;">
                    <div style="display: inline-block; width: 40px; height: 40px; border: 4px solid #f3f3f3;
                                border-top: 4px solid #106bbf; border-radius: 50%; animation: ap-spin 1s linear infinite;">
                    </div>
                    <p style="margin-top: 16px; color: #6b7280;">Loading proctoring report...</p>
                    <style>
                        @keyframes ap-spin {
                            0% { transform: rotate(0deg); }
                            100% { transform: rotate(360deg); }
                        }
                    </style>
                </div>
                <div id="ap-report-content" style="display: none;">
                    <div id="ap-report__overview" style="margin-bottom: 20px;"></div>
                    <div id="ap-report__proctor"></div>
                </div>
                <div id="ap-report__session" style="display: none;"></div>
            `;

            // Add click handler to show report inline
            button.addEventListener("click", () => {
                const container = document.getElementById("ap-review-report-container");

                if (container.style.display === "none") {
                    container.style.display = "block";
                    button.innerHTML = `<i class="fa fa-eye-slash mr-2"></i> Hide Proctoring Report`;

                    // Only load report the first time
                    if (!reportLoaded) {
                        reportLoaded = true;

                        // Load the report using AutoProctor SDK
                        loadReport(clientId, clientSecret, testAttemptId);

                        // Hide loader and show content after a delay
                        setTimeout(() => {
                            const loader = document.getElementById("ap-report-loader");
                            const content = document.getElementById("ap-report-content");
                            if (loader) {
                                loader.style.display = "none";
                            }
                            if (content) {
                                content.style.display = "block";
                            }
                        }, 2000);
                    }

                    // Set up tab click handlers (only once)
                    if (!tabsInitialized) {
                        tabsInitialized = true;
                        setTimeout(() => {
                            const proctoringTab = document.getElementById("proctoring-summary-tab");
                            const sessionTab = document.getElementById("session-recording-tab");

                            proctoringTab?.addEventListener("click", () => switchTab("proctoring-summary-tab"));
                            sessionTab?.addEventListener("click", () => switchTab("session-recording-tab"));
                        }, 100);
                    }
                } else {
                    container.style.display = "none";
                    button.innerHTML = `<i class="fa fa-eye mr-2"></i> ${buttonLabel}`;
                }
            });

            // Also add a link to open in new tab
            const externalLink = document.createElement("a");
            externalLink.href = reportUrl;
            externalLink.target = "_blank";
            externalLink.className = "btn btn-outline-secondary ml-2";
            externalLink.style.cssText = "margin-left: 10px;";
            externalLink.innerHTML = `<i class="fa fa-external-link mr-2"></i> Open in New Tab`;

            buttonContainer.appendChild(button);
            buttonContainer.appendChild(externalLink);

            // Insert after the target element
            targetElement.parentNode.insertBefore(buttonContainer, targetElement.nextSibling);
            targetElement.parentNode.insertBefore(reportContainer, buttonContainer.nextSibling);
        };

        // Start trying to insert the button
        if (document.readyState === "loading") {
            document.addEventListener("DOMContentLoaded", insertButton);
        } else {
            insertButton();
        }
    }

    return {
        init: initAutoProctor,
        loadReport: loadReport,
        addReportButton: addReportButton,
    };
});
