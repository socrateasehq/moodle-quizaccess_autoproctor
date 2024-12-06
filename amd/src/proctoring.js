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
            domain: "https://dev.autoproctor.co",
            environment: "development",
        };
        return creds;
    }

    /**
     * Generates the report options object required by AutoProctor.
     * @returns {object}
     */
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
                $finalSubmitBtn.classList.add("listeners-bound");
                $finalSubmitBtn.addEventListener("click", async () => {
                    if (_apInstance?.isApTestStarted) {
                        await _apInstance?.stop();
                        window.addEventListener("apMonitoringStopped", () => {
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
                    $finishBtn.classList.add("listeners-bound");
                    $finishBtn.addEventListener("click", () => {
                        // Wait for final submit button to appear in the modal
                        waitForElement(MOODLE_FINISH_MODAL_SUBMIT_BTN, handleFinalSubmit, true);
                    });
                },
                true
            );
            return;
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

    return {
        init: initAutoProctor,
        loadReport: loadReport,
    };
});
