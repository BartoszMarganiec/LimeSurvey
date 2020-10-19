<?php


class QuestionAdministrationController extends LSBaseController
{

    /**
     * It's import to have the accessRules set (security issue).
     * Only logged in users should have access to actions. All other permissions
     * should be checked in the action itself.
     *
     * @return array
     */
    public function accessRules()
    {
        return [
            [
                'allow',
                'actions' => [],
                'users'   => ['*'], //everybody
            ],
            [
                'allow',
                'actions' => ['view'],
                'users'   => ['@'], //only login users
            ],
            ['deny'], //always deny all actions not mentioned above
        ];
    }

    /**
     * This part comes from _renderWrappedTemplate
     *
     * @param string $view View
     * 
     * @return bool
     */
    protected function beforeRender($view)
    {
        if (isset($this->aData['surveyid'])) {
            $this->aData['oSurvey'] = $this->aData['oSurvey'] ?? Survey::model()->findByPk($this->aData['surveyid']);

            // Needed to evaluate EM expressions in question summary
            // See bug #11845
            LimeExpressionManager::SetSurveyId($this->aData['surveyid']);
            LimeExpressionManager::StartProcessingPage(false, true);

            $this->layout = 'layout_questioneditor';
        }

        return parent::beforeRender($view);
    }

    /**
     * Renders the main view for question editor.
     * Main view function prepares the necessary global js parts and renders the HTML for the question editor
     *
     * @param integer $surveyid          Survey ID
     * @param integer $gid               Group ID
     * @param integer $qid               Question ID
     * @param string  $landOnSideMenuTab Name of the side menu tab. Default behavior is to land on structure tab.
     *
     * @return void
     *
     * @throws CException
     */
    public function actionView($surveyid, $gid = null, $qid = null, $landOnSideMenuTab = 'structure')
    {
        $aData = [];
        $iSurveyID = (int)$surveyid;

        if (is_null($qid) && !Permission::model()->hasSurveyPermission($iSurveyID, 'surveycontent', 'create')) {
            App()->user->setFlash('error', gT("Access denied"));
            $this->redirect(App()->request->urlReferrer);
        }

        $oSurvey = Survey::model()->findByPk($iSurveyID);

        if ($oSurvey === null) {
            throw new CHttpException(500, "Survey not found $iSurveyID");
        }

        $gid = $gid ?? $oSurvey->groups[0]->gid;
        $oQuestion = $this->getQuestionObject($qid, null, $gid);
        //App()->getClientScript()->registerPackage('questioneditor');
        Yii::app()->clientScript->registerPackage('bootstrap-switch', LSYii_ClientScript::POS_BEGIN);
        App()->getClientScript()->registerPackage('ace');
        $qrrow = $oQuestion->attributes;
        $baselang = $oSurvey->language;

        if (App()->session['questionselectormode'] !== 'default') {
            $questionSelectorType = App()->session['questionselectormode'];
        } else {
            $questionSelectorType = App()->getConfig('defaultquestionselectormode');
        }

        $aData['display']['menu_bars']['gid_action'] = 'viewquestion';
        $aData['questionbar']['buttons']['view'] = true;

        // Last question visited : By user (only one by user)
        $setting_entry = 'last_question_' . App()->user->getId();
        SettingGlobal::setSetting($setting_entry, $oQuestion->qid);

        // we need to set the sid for this question
        $setting_entry = 'last_question_sid_' . App()->user->getId();
        SettingGlobal::setSetting($setting_entry, $iSurveyID);

        // we need to set the gid for this question
        $setting_entry = 'last_question_gid_' . App()->user->getId();
        SettingGlobal::setSetting($setting_entry, $gid);

        // Last question for this survey (only one by survey, many by user)
        $setting_entry = 'last_question_' . App()->user->getId() . '_' . $iSurveyID;
        SettingGlobal::setSetting($setting_entry, $oQuestion->qid);

        // we need to set the gid for this question
        $setting_entry = 'last_question_' . App()->user->getId() . '_' . $iSurveyID . '_gid';
        SettingGlobal::setSetting($setting_entry, $gid);

        ///////////
        // combine aData
        $aData['surveyid'] = $iSurveyID;
        $aData['sid'] = $iSurveyID; //todo duplicated here, because it's needed in some functions of
        $aData['oSurvey'] = $oSurvey;
        $aData['aQuestionTypeList'] = QuestionTheme::findAllQuestionMetaDataForSelector();
        $aData['aQuestionTypeStateList'] = QuestionType::modelsAttributes();
        $aData['selectedQuestion'] = QuestionTheme::findQuestionMetaData($oQuestion->type);
        $aData['gid'] = $gid;
        $aData['qid'] = $oQuestion->qid;
        $aData['activated'] = $oSurvey->active;
        $aData['oQuestion'] = $oQuestion;
        $aData['languagelist'] = $oSurvey->allLanguages;
        $aData['qshowstyle'] = '';
        $aData['qrrow'] = $qrrow;
        $aData['baselang'] = $baselang;
        $aData['sImageURL'] = App()->getConfig('adminimageurl');
        $aData['iIconSize'] = App()->getConfig('adminthemeiconsize');
        $aData['display']['menu_bars']['qid_action'] = 'editquestion';
        $aData['display']['menu_bars']['gid_action'] = 'viewquestion';
        $aData['action'] = 'editquestion';
        $aData['editing'] = true;

        $aData['title_bar']['title'] = $oSurvey->currentLanguageSettings->surveyls_title
            . " (" . gT("ID") . ":" . $iSurveyID . ")";
        $aData['surveyIsActive'] = $oSurvey->active !== 'N';
        $aData['activated'] = $oSurvey->active;
        $aData['jsData'] = [
            'surveyid'             => $iSurveyID,
            'surveyObject'         => $oSurvey->attributes,
            'gid'                  => $gid,
            'qid'                  => $oQuestion->qid,
            'startType'            => $oQuestion->type,
            'baseSQACode'          => [
                'answeroptions' => SettingsUser::getUserSettingValue('answeroptionprefix', App()->user->id) ?? 'AO',
                'subquestions'  => SettingsUser::getUserSettingValue('subquestionprefix', App()->user->id) ?? 'SQ',
            ],
            'startInEditView'      => SettingsUser::getUserSettingValue('noViewMode', App()->user->id) == '1',
            'questionSelectorType' => $questionSelectorType,
            'i10N'                 => [
                'Create question'                                                                              => gT('Create question'),
                'General settings'                                                                             => gT("General settings"),
                'Code'                                                                                         => gT('Code'),
                'Text elements'                                                                                => gT('Text elements'),
                'Question type'                                                                                => gT('Question type'),
                'Question'                                                                                     => gT('Question'),
                'Help'                                                                                         => gT('Help'),
                'subquestions'                                                                                 => gT('Subquestions'),
                'answeroptions'                                                                                => gT('Answer options'),
                'Quick add'                                                                                    => gT('Quick add'),
                'Copy subquestions'                                                                            => gT('Copy subquestions'),
                'Copy answer options'                                                                          => gT('Copy answer options'),
                'Copy default answers'                                                                         => gT('Copy default answers'),
                'Copy advanced options'                                                                        => gT('Copy advanced options'),
                'Predefined label sets'                                                                        => gT('Predefined label sets'),
                'Save as label set'                                                                            => gT('Save as label set'),
                'More languages'                                                                               => gT('More languages'),
                'Add subquestion'                                                                              => gT('Add subquestion'),
                'Reset'                                                                                        => gT('Reset'),
                'Save'                                                                                         => gT('Save'),
                'Some example subquestion'                                                                     => gT('Some example subquestion'),
                'Delete'                                                                                       => gT('Delete'),
                'Open editor'                                                                                  => gT('Open editor'),
                'Duplicate'                                                                                    => gT('Duplicate'),
                'No preview available'                                                                         => gT('No preview available'),
                'Editor'                                                                                       => gT('Editor'),
                'Quick edit'                                                                                   => gT('Quick edit'),
                'Cancel'                                                                                       => gT('Cancel'),
                'Replace'                                                                                      => gT('Replace'),
                'Add'                                                                                          => gT('Add'),
                'Select delimiter'                                                                             => gT('Select delimiter'),
                'Semicolon'                                                                                    => gT('Semicolon'),
                'Comma'                                                                                        => gT('Comma'),
                'Tab'                                                                                          => gT('Tab'),
                'New rows'                                                                                     => gT('New rows'),
                'Scale'                                                                                        => gT('Scale'),
                'Save and Close'                                                                               => gT('Save and close'),
                'Script'                                                                                       => gT('Script'),
                'X-Scale (columns)'                                                                            => gT('X-Scale (columns)'),
                'Y-Scale (lines)'                                                                              => gT('Y-Scale (lines)'),
                "noCodeWarning" =>
                    gT("Please put in a valid code. Only letters and numbers are allowed and it has to start with a letter. For example [Question1]"),
                "alreadyTaken" =>
                    gT("This code is already used - duplicate codes are not allowed."),
                "codeTooLong" =>
                    gT("A question code cannot be longer than 20 characters."),
                "Question cannot be stored. Please check the subquestion codes for duplicates or empty codes." =>
                    gT("Question cannot be stored. Please check the subquestion codes for duplicates or empty codes."),
                "Question cannot be stored. Please check the answer options for duplicates or empty codes." =>
                    gT("Question cannot be stored. Please check the answer options for duplicates or empty codes."),
            ],
        ];

        $aData['topBar']['type'] = 'question';

        $aData['topBar']['importquestion'] = true;
        $aData['topBar']['showSaveButton'] = true;
        $aData['topBar']['savebuttonform'] = 'frmeditgroup';
        $aData['topBar']['closebuttonurl'] = '/questionAdministration/listquestions/surveyid/' . $iSurveyID; // Close button

        if ($landOnSideMenuTab !== '') {
            $aData['sidemenu']['landOnSideMenuTab'] = $landOnSideMenuTab;
        }

        $this->aData = $aData;

        $this->render(
            'view',
            [
                'oQuestion'              => $oQuestion,
                'oSurvey'                => $oSurvey,
                'questionTypes'          => QuestionType::modelsAttributes(),
                'answersCount'           => Answer::model()->countByAttributes(array('qid' => $qid)),
                'subquestionsCount'      => Question::model()->countByAttributes(array('parent_qid' => $qid)),
                'advancedSettings'       => $this->getAdvancedOptions($oQuestion->qid, $oQuestion->type, 'core'),  // TODO: question_template
                'generalSettings'        => $this->getGeneralOptions(
                                                $oQuestion->qid,
                                                $oQuestion->type,
                                                $oQuestion->gid,
                                                'core'  // TODO: question_template
                                            ),
                'jsData'                 => $aData['jsData'],
                'aQuestionTypeList'      => $aData['aQuestionTypeList'],
                'aQuestionTypeStateList' => $aData['aQuestionTypeStateList'],
                'aQuestionTypeGroups'    => $this->getQuestionTypeGroups($aData['aQuestionTypeList'])
            ]
        );
    }

    /**
     * Show form to create new question.
     *
     * @param int $surveyid
     * @return void
     */
    public function actionCreate($surveyid)
    {
        $surveyid = (int) $surveyid;

        if (!Permission::model()->hasSurveyPermission($surveyid, 'surveycontent', 'create')) {
            App()->user->setFlash('error', gT("Access denied"));
            $this->redirect(App()->request->urlReferrer);
        }

        $oSurvey = Survey::model()->findByPk($surveyid);
        if (empty($oSurvey)) {
            throw new Exception('Internal error: Found no survey with id ' . $surveyid);
        }

        $oQuestion = $this->getQuestionObject(null, 'F', null);

        Yii::app()->loadHelper("admin.htmleditor");
        Yii::app()->getClientScript()->registerPackage('ace');
        Yii::app()->getClientScript()->registerPackage('jquery-ace');
        Yii::app()->getClientScript()->registerScript(
            'editorfiletype',
            "editorfiletype ='javascript';",
            CClientScript::POS_HEAD
        );
        App()->getClientScript()->registerScriptFile(
            App()->getConfig('adminscripts') . 'questionEditor.js',
            CClientScript::POS_BEGIN
        );
        // TODO: No difference between true and false?
        PrepareEditorScript(false, $this);
        App()->session['FileManagerContent'] = "edit:survey:{$surveyid}";
        initKcfinder();

        $this->aData['surveyid'] = $surveyid;
        $this->aData['sid'] = $surveyid;
        $this->aData['display']['menu_bars']['gid_action'] = 'viewquestion';
        $this->aData['questionbar']['buttons']['view'] = true;
        $this->aData['title_bar']['title'] =
            $oSurvey->currentLanguageSettings->surveyls_title
            . " (" . gT("ID") . ":" . $surveyid . ")";
        $this->aData['aQuestionTypeList'] = QuestionTheme::findAllQuestionMetaDataForSelector();
        $advancedSettings = $this->getAdvancedOptions(0, 'T', 'core');  // TODO: question_template
        // Remove general settings from this array.
        unset($advancedSettings['Attribute']);

        $this->render(
            'create',
            [
                'oSurvey'                => $oSurvey,
                'oQuestion'              => $oQuestion,
                'aQuestionTypeGroups'    => $this->getQuestionTypeGroups($this->aData['aQuestionTypeList']),
                'aQuestionTypeStateList' => QuestionType::modelsAttributes(),
                'advancedSettings'       => $advancedSettings,
                'generalSettings'        => $this->getGeneralOptions(0, 'T', 0, 'core')  // TODO: question_template
            ]
        );
    }

    /**
     * Load list questions view for a specified survey by $surveyid
     *
     * @param int $surveyid Goven Survey ID
     * @param string  $landOnSideMenuTab Name of the side menu tab. Default behavior is to land on settings tab.
     *
     * @return string
     * @access public
     * @todo   php warning (Missing return statement)
     */
    public function actionListQuestions($surveyid, $landOnSideMenuTab = 'settings')
    {
        $iSurveyID = sanitize_int($surveyid);
        // Reinit LEMlang and LEMsid: ensure LEMlang are set to default lang, surveyid are set to this survey id
        // Ensure Last GetLastPrettyPrintExpression get info from this sid and default lang
        LimeExpressionManager::SetEMLanguage(Survey::model()->findByPk($iSurveyID)->language);
        LimeExpressionManager::SetSurveyId($iSurveyID);
        LimeExpressionManager::StartProcessingPage(false, true);

        // Set number of page
        $pageSize = App()->request->getParam('pageSize', null);
        if ($pageSize != null) {
            App()->user->setState('pageSize', (int) $pageSize);
        }

        $oSurvey = Survey::model()->findByPk($iSurveyID);
        $aData   = array();

        $aData['oSurvey']                               = $oSurvey;
        $aData['surveyid']                              = $iSurveyID;
        $aData['sid']                                   = $iSurveyID;
        $aData['display']['menu_bars']['listquestions'] = true;
        $aData['sidemenu']['listquestions']             = true;
        $aData['sidemenu']['landOnSideMenuTab']         = $landOnSideMenuTab;
        $aData['surveybar']['returnbutton']['url']      = $this->createUrl(
            "/surveyAdministration/listsurveys"
        );
        $aData['surveybar']['returnbutton']['text']     = gT('Return to survey list');
        $aData['surveybar']['buttons']['newquestion']   = true;

        $aData["surveyHasGroup"]        = $oSurvey->groups;
        $aData['subaction']             = gT("Questions in this survey");
        $aData['title_bar']['title']    = $oSurvey->currentLanguageSettings->surveyls_title.
            " (".gT("ID").":".$iSurveyID.")";

        // The DataProvider will be build from the Question model, search method
        $model = new Question('search');
        // Global filter
        if (isset($_GET['Question'])) {
            $model->setAttributes($_GET['Question'], false);
        }
        // Filter group
        if (isset($_GET['gid'])) {
            $model->gid = $_GET['gid'];
        }
        // Set number of page
        if (isset($_GET['pageSize'])) {
            App()->user->setState('pageSize', (int) $_GET['pageSize']);
        }
        $aData['pageSize'] = App()->user->getState('pageSize', App()->params['defaultPageSize']);
        // We filter the current survey id
        $model->sid = $oSurvey->sid;
        $aData['model'] = $model;
        $this->aData = $aData;

        $this->render("listquestions", $aData);
    }

    /****
     * *** A lot of getter function regarding functionalities and views.
     * *** All called via ajax
     ****/

    /**
     * Returns all languages in a specific survey as a JSON document
     *
     * todo: is this action still in use?? where in the frontend?
     *
     * @param int $iSurveyId
     *
     * @return void
     */
    public function actionGetPossibleLanguages($iSurveyId)
    {
        $iSurveyId = (int)$iSurveyId;
        $aLanguages = Survey::model()->findByPk($iSurveyId)->allLanguages;
        $this->renderJSON($aLanguages);
    }

    /**
     * Action called by the FE editor when a save is triggered.
     * This is called for both new question and update.
     *
     * @return void
     * @throws CException
     * @todo Different permission check when sid vs qid is given.
     */
    public function actionSaveQuestionData()
    {
        echo '<pre>'; print_r($_POST); echo '</pre>';
        die;
        $request = App()->request;
        $iSurveyId = (int) $request->getPost('sid');
        if (!Permission::model()->hasSurveyPermission($iSurveyId, 'surveycontent', 'update')) {
            Yii::app()->user->setFlash('error', gT("Access denied"));
            $this->redirect(Yii::app()->request->urlReferrer);
        }

        /**
         * From Vue component:
            questionData: {
            question: context.state.currentQuestion,
                scaledSubquestions:
            context.state.currentQuestionSubquestions || [],
                scaledAnswerOptions:
            context.state.currentQuestionAnswerOptions || [],
                questionI10N: context.state.currentQuestionI10N,
                generalSettings:
            context.state.currentQuestionGeneralSettings,
                advancedSettings:
            context.state.currentQuestionAdvancedSettings
        }
         */

        $questionData = [];
        $questionData['question']         = (array) $request->getPost('question');
        $questionData['questionI10N']     = (array) $request->getPost('questionI10N');
        $questionData['advancedSettings'] = (array) $request->getPost('advancedSettings');
        $questionData['question']['sid']  = $iSurveyId;

        /*
         * Setting up a try/catch scenario to delete a copied/created question,
         * in case the storing of the peripherals breaks
         */
        try {
            $transaction = Yii::app()->db->beginTransaction();

            if ($questionData['question']['qid'] == 0) {
                $questionData['question']['qid'] = null;
                $oQuestion = $this->storeNewQuestionData($questionData['question']);
            } else {
                // Store changes to the actual question data, by either storing it, or updating an old one
                $oQuestion = Question::model()->find(
                    'sid = :sid AND qid = :qid',
                    [':sid' => $iSurveyId, ':qid' => (int) $questionData['question']['qid']]
                );
                $oQuestion = $this->updateQuestionData($oQuestion, $questionData['question']);
            }

            // Apply the changes to general settings, advanced settings and translations
            $setApplied = [];

            $setApplied['questionI10N'] = $this->applyI10N($oQuestion, $questionData['questionI10N']);

            $setApplied['advancedSettings'] = $this->unparseAndSetAdvancedOptions(
                $oQuestion,
                $questionData['advancedSettings']
            );

            $setApplied['question'] = $this->unparseAndSetGeneralOptions(
                $oQuestion,
                $questionData['question']
            );

            // save advanced attributes default values for given question type
            if (array_key_exists('save_as_default', $questionData['question'])
                && $questionData['question']['save_as_default'] == 'Y') {
                SettingsUser::setUserSetting(
                    'question_default_values_' . $questionData['question']['type'],
                    ls_json_encode($questionData['advancedSettings'])
                );
            } elseif (array_key_exists('clear_default', $questionData['question'])
                && $questionData['question']['clear_default'] == 'Y') {
                SettingsUser::deleteUserSetting('question_default_values_' . $questionData['question']['type']);
            }


            // Clean subquestions and answer options before save.
            // NB: Still inside a database transaction.
            if ($oQuestion->survey->active == 'N') {
                $oQuestion->deleteAllAnswers();
                // If question type has subquestions, save them.
                if ($oQuestion->questionType->subquestions === 1) {
                    $this->storeSubquestions(
                        $oQuestion,
                        $request->getPost('subquestions')
                    );
                }
                // If question type has answeroptions, save them.
                if ($oQuestion->questionType->answerscales > 0) {
                    //$this->storeAnswerOptions(
                        //$oQuestion,
                        //$request->getPost('answeroptions')
                    //);
                }
            } else {
                // Can only update subquestions.
                // TODO: Update subquestions.
                // TODO: Update answer options.
            }
            $transaction->commit();
        } catch (CException $ex) {
            $transaction->rollback();
            throw new LSJsonException(
                500,
                gT('An error happened:') . "\n" . $ex->getMessage() . PHP_EOL
                . $ex->getTraceAsString(),
                0,
                App()->createUrl(
                    'surveyAdministration/view/',
                    ["surveyid" => $iSurveyId]
                )
            );
        }

        // TODO: Redirect to edit form.
        // Return success message.
        $this->renderJSON(
            [
                'success' => true,
                'message' => gT('Question successfully stored'),
            ]
        );
        // TODO: Needed?
        App()->close();
    }

    /**
     * Update the data set in the FE
     *
     * @param int $iQuestionId
     * @param string $type
     * @param int $gid Group id
     * @param string $question_template
     *
     * @return void
     * @throws CException
     */
    public function actionReloadQuestionData(
        $iQuestionId = null,
        $type = null,
        $gid = null,
        $question_template = 'core'
    ) {
        $iQuestionId = (int)$iQuestionId;
        $oQuestion = $this->getQuestionObject($iQuestionId, $type, $gid);

        $aCompiledQuestionData = $this->getCompiledQuestionData($oQuestion);
        $aQuestionGeneralOptions = $this->getGeneralOptions(
            $oQuestion->qid,
            $type,
            $oQuestion->gid,
            $question_template
        );
        $aAdvancedOptions = $this->getAdvancedOptions($oQuestion->qid, $type, $question_template);

        $aLanguages = [];
        $aAllLanguages = getLanguageData(false, App()->session['adminlang']);
        $aSurveyLanguages = $oQuestion->survey->getAllLanguages();

        array_walk(
            $aSurveyLanguages,
            function ($lngString) use (&$aLanguages, $aAllLanguages) {
                $aLanguages[$lngString] = $aAllLanguages[$lngString]['description'];
            }
        );

        $this->renderJSON(
            array_merge(
                $aCompiledQuestionData,
                [
                    'languages'        => $aLanguages,
                    'mainLanguage'     => $oQuestion->survey->language,
                    'generalSettings'  => $aQuestionGeneralOptions,
                    'advancedSettings' => $aAdvancedOptions,
                    'questiongroup'    => $oQuestion->group->attributes,
                ]
            )
        );
    }

    /**
     * @todo document me
     *
     * @param int $iQuestionId
     * @param string $sQuestionType
     * @param int $gid
     * @param boolean $returnArray
     * @param string $question_template
     *
     * @return void|array
     * @throws CException
     */
    public function actionGetGeneralOptions(
        $iQuestionId = null,
        $sQuestionType = null,
        $gid = null,
        $returnArray = false,  //todo see were this ajaxrequest is done and take out the parameter there and here
        $question_template = 'core'
    ) {
        $aGeneralOptionsArray = $this->getGeneralOptions($iQuestionId, $sQuestionType, $gid, $question_template);

        $this->renderJSON($aGeneralOptionsArray);
    }


    /**
     * Action (called by ajaxrequest and returning json)
     * Returns a preformatted json of advanced settings.
     *
     * @param int $iQuestionId
     * @param string $sQuestionType
     * @param boolean $returnArray
     * @param string $question_template
     *
     * @return void|array
     * @throws CException
     * @todo Delete when Vue is gone?
     */
    public function actionGetAdvancedOptions(
        $iQuestionId = null,
        $sQuestionType = null,
        $returnArray = false, //todo see were this ajaxrequest is done and take out the parameter there and here
        $question_template = 'core'
    ) {
        //here we get a Question object (also if question is new --> QuestionCreate)
        $oQuestion = $this->getQuestionObject($iQuestionId, $sQuestionType);
        $aAdvancedOptionsArray = $this->getAdvancedOptions($iQuestionId, $sQuestionType, $question_template);

        $this->renderJSON(
            [
                'advancedSettings'       => $aAdvancedOptionsArray,
                'questionTypeDefinition' => $oQuestion->questionType,
            ]
        );
    }


    /**
     * Collect initial question data
     * This either creates a temporary question object, or calls a question object from the database
     *
     * @param int $iQuestionId
     * @param int $gid
     * @param string $type
     *
     * @return void
     * @throws CException
     */
    public function actionGetQuestionData($iQuestionId = null, $gid = null, $type = null)
    {
        $iQuestionId = (int)$iQuestionId;
        $oQuestion = $this->getQuestionObject($iQuestionId, $type, $gid);

        $aQuestionInformationObject = $this->getCompiledQuestionData($oQuestion);
        $surveyInfo = $this->getCompiledSurveyInfo($oQuestion);

        $aLanguages = [];
        $aAllLanguages = getLanguageData(false, App()->session['adminlang']);
        $aSurveyLanguages = $oQuestion->survey->getAllLanguages();
        array_walk(
            $aSurveyLanguages,
            function ($lngString) use (&$aLanguages, $aAllLanguages) {
                $aLanguages[$lngString] = $aAllLanguages[$lngString]['description'];
            }
        );

        $this->renderJSON(
            array_merge(
                $aQuestionInformationObject,
                [
                    'surveyInfo'   => $surveyInfo,
                    'languages'    => $aLanguages,
                    'mainLanguage' => $oQuestion->survey->language,
                ]
            )
        );
    }

    /**
     * Called via Ajax.
     *
     * @param int $surveyid
     * @param int $gid
     * @param string $codes
     * @param int $scale_id
     * @param int $position
     * @param string $assessmentvisible
     * @return void
     * @todo Permission check hard with both sid and gid.
     */
    public function actionGetSubquestionRowForAllLanguages($surveyid, $gid, $codes, $scale_id, $position = 0, $assessmentvisible = '')
    {
        $oSurvey = Survey::model()->findByPk($surveyid);
        if (empty($oSurvey)) {
            throw new CHttpException(404, gT("Invalid survey id"));
        }
        if (!Permission::model()->hasSurveyPermission($oSurvey->sid, 'surveycontent', 'update')) {
            throw new CHttpException(403, gT("No permission"));
        }
        $html  = [];
        $first = true;
        $qid   = 'new' . rand(0, 99999);
        foreach ($oSurvey->allLanguages as $language) {
            $html[$language] = $this->getSubquestionRow($oSurvey->sid, $gid, $qid, $codes, $language, $first, $scale_id, 'subquestion', $position, $assessmentvisible);
            $first = false;
        }
        header('Content-Type: application/json');
        echo json_encode($html);
    }

    /**
     * AJAX Method to QuickAdd multiple Rows AJAX-based
     * @todo Permission
     */
    public function getSubquestionRowQuickAdd($surveyid, $gid)
    {
        $qid               = '{{quid_placeholder}}';
        $request           = Yii::app()->request;
        $codes             = $request->getPost('codes');
        $language          = $request->getPost('language');
        $first             = $request->getPost('first');
        $scale_id          = $request->getPost('scale_id');
        $type              = $request->getPost('type');
        $position          = $request->getPost('position');
        $assessmentvisible = $request->getPost('assessmentvisible');
        echo $this->getSubquestionRow($surveyid, $gid, $qid, $codes, $language, $first, $scale_id, $type, $position, $assessmentvisible);
    }

    /**
     * 
     * @return 
     */
    public function actionGetAnswerOptionRowForAllLanguages()
    {
        die('here');
    }

    /**
     * This function should be called via ajax request
     * It returns a EMPTY subquestion row HTML for a given ....
     */
    private function getSubquestionRow($surveyid, $gid, $qid, $codes, $language, $first, $scale_id, $type, $position, $assessmentvisible = '')
    {
        // index.php/admin/questions/sa/getSubquestionRow/position/1/scale_id/1/surveyid/691948/gid/76/qid/1611/language/en/first/true
        $stringCodes = json_decode($codes,true); // All the codes of the displayed subquestions

        // TODO: calcul correct value
        $oldCode = false;

        $oQuestion = $this->getQuestionObject($qid, $type, $gid);
        $oSubquestion = $oQuestion->getEmptySubquestion();
        $oSubquestion->qid = $qid;  // Set qid as new12345 random id.

        //Capture "true" and "false" as strings
        if (is_string($first)) {
            $first = ($first == "false" ? false : true);
        }
        // We get the numerical part of each code and we store them in Arrays
        // One array is to store the pure numerical values (so we can search in it for the greates value, and increment it)
        // Another array is to store the string values (so we keep all the prefixed "0")
        $numCodes = array();
        foreach ($stringCodes as $key => $stringCode) {
            // This will loop into the code, from the last character to the first letter
            $numericSuffix = ''; $n = 1; $numeric = true;
            while ($numeric === true && $n <= strlen($stringCode)) {
                $currentCharacter = (string) substr($stringCode, -$n, 1); // get the current character

                if (ctype_digit($currentCharacter)) {
                // check if it's numerical
                    $numericSuffix = $currentCharacter.$numericSuffix; // store it in a string
                    $n = $n + 1;
                } else {
                    $numeric = false; // At first non numeric character found, the loop is stoped
                }
            }
            $numCodesWithZero[$key] = (string) $numericSuffix; // In string type, we can have   : "0001"
            $numCodes[$key]         = (int) $numericSuffix; // In int type, we can only have : "1"
        }

        // Let's get the greatest code
        $greatestNumCode          = max($numCodes); // greatest code
        $key                      = array_keys($numCodes, max($numCodes)); // its key (same key in all tables)
        $greatesNumCodeWithZeros  = (isset($numCodesWithZero)) ? $numCodesWithZero[$key[0]] : ''; // its value with prefixed 0 (like : 001)
        $stringCodeOfGreatestCode = $stringCodes[$key[0]]; // its original submited  string (like: SQ001)

        // We get the string part of it: it's the original string code, without the greates code with its 0 :
        // like  substr ("SQ001", (strlen(SQ001)) - strlen(001) ) ==> "SQ"
        $stringPartOfNewCode    = (string) substr($stringCodeOfGreatestCode, 0, (strlen($stringCodeOfGreatestCode) - strlen($greatesNumCodeWithZeros)));

        // We increment by one the greatest code
        $numericalPartOfNewCode = $newPosition = $greatestNumCode + 1;

        // We get the list of 0 : (using $numericalPartOfNewCode will remove the excedent 0 ; SQ009 will be followed by SQ010 )
        $listOfZero = (string) substr($greatesNumCodeWithZeros, 0, (strlen($greatesNumCodeWithZeros) - strlen($numericalPartOfNewCode)));

        // When no more zero are available we want to be sure that the last 9 unit will not left
        // (like in SQ01 => SQ99 ; should become SQ100, not SQ9100)
        $listOfZero = ($listOfZero == "9") ? '' : $listOfZero;

        // We finaly build the new code
        $code = $stringPartOfNewCode.$listOfZero.$numericalPartOfNewCode;

        $activated = false; // You can't add ne subquestion when survey is active
        Yii::app()->loadHelper('admin/htmleditor'); // Prepare the editor helper for the view

        if ($type == 'subquestion') {
            $view = 'subquestionRow.twig';
            $aData = array(
                'position'  => $position,
                'scale_id'  => $scale_id,
                'activated' => $activated,
                'first'     => $first,
                'surveyid'  => $surveyid,
                'gid'       => $gid,
                'qid'       => $qid,
                'language'  => $language,
                'title'     => $code,
                'question'  => '',
                'relevance' => '1',
                'oldCode'   => $oldCode,
                'subquestion'  => $oSubquestion
            );
        } else {
            $view = 'answerOptionRow.twig';
            $aData = array(
                'assessmentvisible' => $assessmentvisible == "false" ? false : true,
                'assessment_value'  => '',
                'answer'            => '',
                'sortorder'         => $newPosition,
                'position'          => $newPosition,
                'scale_id'          => $scale_id,
                'activated'         => $activated,
                'first'             => $first,
                'surveyid'          => $surveyid,
                'gid'               => $gid,
                'qid'               => $qid,
                'language'          => $language,
                'title'             => $code,
                'question'          => '',
                'relevance'         => '1',
                'oldCode'           => $oldCode,
            );
        }

        $html = '<!-- Inserted Row -->';
        $html .= App()->twigRenderer->renderPartial('/questionAdministration/' . $view, $aData);
        $html .= '<!-- end of Inserted Row -->';
        return $html;
    }

    /**
     * Collect the permissions available for a specific question
     *
     * @param $iQuestionId
     *
     * @return void
     * @throws CException
     */
    public function actionGetQuestionPermissions($iQuestionId = null)
    {
        $iQuestionId = (int)$iQuestionId;
        $oQuestion = $this->getQuestionObject($iQuestionId);

        $aPermissions = [
            "read"         => Permission::model()->hasSurveyPermission($oQuestion->sid, 'surveycontent', 'read'),
            "update"       => Permission::model()->hasSurveyPermission($oQuestion->sid, 'surveycontent', 'update'),
            "editorpreset" => App()->session['htmleditormode'],
            "script"       =>
                Permission::model()->hasSurveyPermission($oQuestion->sid, 'surveycontent', 'update')
                && SettingsUser::getUserSetting('showScriptEdit', App()->user->id),
        ];

        $this->renderJSON($aPermissions);
    }

    /**
     * Returns a json document containing the question types
     *
     * @return void
     */
    public function actionGetQuestionTypeList()
    {
        $this->renderJSON(QuestionType::modelsAttributes());
    }

    /**
     * @todo document me.
     * @todo is this used in frontend somewherer? can't find it
     *
     * @param string $sQuestionType
     * @return void
     */
    public function actionGetQuestionTypeInformation($sQuestionType)
    {
        $aTypeInformations = QuestionType::modelsAttributes();
        $aQuestionTypeInformation = $aTypeInformations[$sQuestionType];

        $this->renderJSON($aQuestionTypeInformation);
    }

    /**
     * Renders the top bar definition for questions as JSON document
     *
     * @param int $qid
     * @return false|null|string|string[]
     * @throws CException
     */
    public function actionGetQuestionTopbar($qid = null)
    {
        $oQuestion = $this->getQuestionObject($qid);
        $sid = $oQuestion->sid;
        $gid = $oQuestion->gid;
        $qid = $oQuestion->qid;
        $questionTypes = QuestionType::modelsAttributes();
        // TODO: Rename Variable for better readability.
        $qrrow = $oQuestion->attributes;
        $ownsSaveButton = true;
        $ownsImportButton = true;

        $hasCopyPermission = Permission::model()->hasSurveyPermission($sid, 'surveycontent', 'create');
        $hasUpdatePermission = Permission::model()->hasSurveyPermission($sid, 'surveycontent', 'update');
        $hasExportPermission = Permission::model()->hasSurveyPermission($sid, 'surveycontent', 'export');
        $hasDeletePermission = Permission::model()->hasSurveyPermission($sid, 'surveycontent', 'delete');
        $hasReadPermission = Permission::model()->hasSurveyPermission($sid, 'surveycontent', 'read');

        return $this->renderPartial(
            'question_topbar',
            [
                'oSurvey'             => $oQuestion->survey,
                'sid'                 => $sid,
                'hasCopyPermission'   => $hasCopyPermission,
                'hasUpdatePermission' => $hasUpdatePermission,
                'hasExportPermission' => $hasExportPermission,
                'hasDeletePermission' => $hasDeletePermission,
                'hasReadPermission'   => $hasReadPermission,
                'gid'                 => $gid,
                'qid'                 => $qid,
                'qrrow'               => $qrrow,
                'qtypes'              => $questionTypes,
                'ownsSaveButton'      => $ownsSaveButton,
                'ownsImportButton'    => $ownsImportButton,
            ],
            false,
            false
        );
    }

    /**
     * Display import view for Question
     *
     * @param int $surveyid
     * @param int|null $groupid
     */
    public function actionImportView($surveyid, $groupid = null)
    {
        $iSurveyID = (int)$surveyid;
        if (!Permission::model()->hasSurveyPermission($iSurveyID, 'surveycontent', 'import')) {
            App()->session['flashmessage'] = gT("We are sorry but you don't have permissions to do this.");
            $this->redirect(['questionAdministration/listquestions/surveyid/' . $iSurveyID]);
        }
        $survey = Survey::model()->findByPk($iSurveyID);
        $aData = [];
        $aData['sidemenu']['state'] = false;
        $aData['sidemenu']['questiongroups'] = true;
        $aData['surveybar']['closebutton']['url'] = '/questionGroupsAdministration/listquestiongroups/surveyid/' . $iSurveyID; // Close button
        $aData['surveybar']['savebutton']['form'] = true;
        $aData['surveybar']['savebutton']['text'] = gt('Import');
        $aData['sid'] = $iSurveyID;
        $aData['surveyid'] = $iSurveyID; // todo duplication needed for survey_common_action
        $aData['gid'] = $groupid;
        $aData['topBar']['showSaveButton'] = true;
        $aData['topBar']['showCloseButton'] = true;
        $aData['title_bar']['title'] = $survey->currentLanguageSettings->surveyls_title . " (" . gT("ID") . ":" . $iSurveyID . ")";

        $this->aData = $aData;
        $this->render(
            'importQuestion',
            [
                'gid' => $aData['gid'],
                'sid' => $aData['sid']
            ]
        );
    }

    /**
     * Import the Question
     */
    public function actionImport()
    {
        $iSurveyID = App()->request->getPost('sid', 0);
        $gid = App()->request->getPost('gid', 0);

        $jumptoquestion = (bool)App()->request->getPost('jumptoquestion', 1);

        $oSurvey = Survey::model()->findByPk($iSurveyID);

        $aData = [];
        $aData['display']['menu_bars']['surveysummary'] = 'viewquestion';
        $aData['display']['menu_bars']['gid_action'] = 'viewgroup';

        $sFullFilepath = App()->getConfig('tempdir') . DIRECTORY_SEPARATOR . randomChars(20);
        $sExtension = pathinfo($_FILES['the_file']['name'], PATHINFO_EXTENSION);
        $fatalerror = '';

        if ($_FILES['the_file']['error'] == 1 || $_FILES['the_file']['error'] == 2) {
            $fatalerror = sprintf(
                    gT("Sorry, this file is too large. Only files up to %01.2f MB are allowed."),
                    getMaximumFileUploadSize() / 1024 / 1024
                ) . '<br>';
        } elseif (!@move_uploaded_file($_FILES['the_file']['tmp_name'], $sFullFilepath)) {
            $fatalerror = gT(
                    "An error occurred uploading your file."
                    . " This may be caused by incorrect permissions for the application /tmp folder."
                ) . '<br>';
        }

        // validate that we have a SID and GID
        if (!$iSurveyID) {
            $fatalerror .= gT("No SID (Survey) has been provided. Cannot import question.");
        }

        if (!$gid) {
            $fatalerror .= gT("No GID (Group) has been provided. Cannot import question");
        }

        if ($fatalerror != '') {
            unlink($sFullFilepath);
            App()->setFlashMessage($fatalerror, 'error');
            $this->redirect('questionAdministration/importView/surveyid/' . $iSurveyID);
            return;
        }

        // load import_helper and import the file
        App()->loadHelper('admin/import');
        $aImportResults = [];
        if (strtolower($sExtension) === 'lsq') {
            $aImportResults = XMLImportQuestion(
                $sFullFilepath,
                $iSurveyID,
                $gid,
                [
                    'autorename'      => App()->request->getPost('autorename') == '1',
                    'translinkfields' => App()->request->getPost('autorename') == '1'
                ]
            );
        } else {
            App()->setFlashMessage(gT('Unknown file extension'), 'error');
            $this->redirect('questionAdministration/importView/surveyid/' . $iSurveyID);
            return;
        }

        fixLanguageConsistency($iSurveyID);

        if (isset($aImportResults['fatalerror'])) {
            App()->setFlashMessage($aImportResults['fatalerror'], 'error');
            $this->redirect('questionAdministration/importView/surveyid/' . $iSurveyID);
            return;
        }

        unlink($sFullFilepath);

        $aData['aImportResults'] = $aImportResults;
        $aData['sid'] = $iSurveyID;
        $aData['surveyid'] = $iSurveyID; // todo needed in function beforeRender in this controller
        $aData['gid'] = $gid;
        $aData['sExtension'] = $sExtension;

        if ($jumptoquestion) {
            App()->setFlashMessage(gT("Question imported successfully"), 'success');
            $this->redirect(
                App()->createUrl(
                    'questionAdministration/view/',
                    [
                        'surveyid' => $iSurveyID,
                        'gid'      => $gid,
                        'qid'      => $aImportResults['newqid']
                    ]
                )
            );
            return;
        }

        $aData['sidemenu']['state'] = false; // todo ignored by sidebar.vue
        $aData['sidemenu']['landOnSideMenuTab'] = 'structure';
        $aData['title_bar']['title'] = $oSurvey->defaultlanguage->surveyls_title . " (" . gT("ID") . ":" . $iSurveyID . ")";

        $this->aData = $aData;
        $this->render(
            'import',
            [
                'aImportResults' => $aData['aImportResults'],
                'sExtension'     => $aData['sExtension'],
                'sid'            => $aData['sid'],
                'gid'            => $aData['gid']
            ]
        );
    }

    /**
     * Load edit default values of a question screen
     *
     * @access public
     * @param int $surveyid
     * @param int $gid
     * @param int $qid
     * @return void
     */
    public function actionEditdefaultvalues($surveyid, $gid, $qid)
    {
        if (!Permission::model()->hasSurveyPermission($surveyid, 'surveycontent', 'update')) {
            App()->user->setFlash('error', gT("Access denied"));
            $this->redirect(App()->request->urlReferrer);
        }
        $iSurveyID = (int)$surveyid;
        $gid = (int)$gid;
        $qid = (int)$qid;
        $oQuestion = Question::model()->findByAttributes(['qid' => $qid, 'gid' => $gid,]);
        $aQuestionTypeMetadata = QuestionType::modelsAttributes();
        $oSurvey = Survey::model()->findByPk($iSurveyID);

        $oDefaultValues = self::getDefaultValues($iSurveyID, $gid, $qid);

        $aData = [
            'oQuestion'    => $oQuestion,
            'qid'          => $qid,
            'sid'          => $iSurveyID,
            'surveyid'     => $iSurveyID, // todo needed in beforeRender
            'langopts'     => $oDefaultValues,
            'questionrow'  => $oQuestion->attributes,
            'gid'          => $gid,
            'qtproperties' => $aQuestionTypeMetadata,
        ];
        $aData['title_bar']['title'] = $oSurvey->currentLanguageSettings->surveyls_title . " (" . gT("ID") . ":" . $iSurveyID . ")";
        $aData['questiongroupbar']['savebutton']['form'] = 'frmeditgroup';
        $aData['questiongroupbar']['closebutton']['url'] = 'questionAdministration/view/surveyid/' . $iSurveyID . '/gid/' . $gid . '/qid/' . $qid; // Close button
        $aData['questiongroupbar']['saveandclosebutton']['form'] = 'frmeditgroup';
        $aData['display']['menu_bars']['surveysummary'] = 'editdefaultvalues';
        $aData['display']['menu_bars']['qid_action'] = 'editdefaultvalues';
        $aData['sidemenu']['state'] = false;
        $aData['sidemenu']['explorer']['state'] = true;
        $aData['sidemenu']['explorer']['gid'] = (isset($gid)) ? $gid : false;
        $aData['sidemenu']['explorer']['qid'] = (isset($qid)) ? $qid : false;
        $aData['topBar']['showSaveButton'] = true;
        $aData['topBar']['showCloseButton'] = true;
        $aData['topBar']['closeButtonUrl'] = $this->createUrl(
            'questionAdministration/view/',
            ['sid' => $iSurveyID, 'gid' => $gid, 'qid' => $qid]
        );
        $aData['hasUpdatePermission'] = Permission::model()->hasSurveyPermission(
            $iSurveyID,
            'surveycontent',
            'update'
        ) ? '' : 'disabled="disabled" readonly="readonly"';
        $aData['oSurvey'] = $oSurvey;

        $this->aData = $aData;
        $this->render('editdefaultvalues', $aData);
    }

    /**
     * Delete multiple questions.
     * Called by ajax from question list.
     * Permission check is done by questions::delete()
     *
     * @return void
     * @throws CException
     */
    public function actionDeleteMultiple()
    {
        $aQids = json_decode(Yii::app()->request->getPost('sItems'));
        $aResults = [];

        foreach ($aQids as $iQid) {
            $oQuestion = Question::model()->with('questionl10ns')->findByPk($iQid);
            $oSurvey = Survey::model()->findByPk($oQuestion->sid);
            $sBaseLanguage = $oSurvey->language;

            if (is_object($oQuestion)) {
                $aResults[$iQid]['title'] = viewHelper::flatEllipsizeText(
                    $oQuestion->questionl10ns[$sBaseLanguage]->question,
                    true,
                    0
                );
                $result = $this->actionDelete($iQid, true);
                $aResults[$iQid]['result'] = $result['status'];
            }
        }

        $this->renderPartial(
            'ext.admin.survey.ListSurveysWidget.views.massive_actions._action_results',
            ['aResults' => $aResults, 'successLabel' => gT('Deleted')]
        );
    }

    /**
     * Function responsible for deleting a question.
     *
     * @access public
     * @param int $qid
     * @param bool $massAction
     * @param string $redirectTo Redirect to question list ('questionlist' or empty), or group overview ('groupoverview')
     * @return array|void
     * @throws CDbException
     * @throws CHttpException
     */
    public function actionDelete($qid = null, $massAction = false, $redirectTo = null)
    {
        if (is_null($qid)) {
            $qid = Yii::app()->getRequest()->getPost('qid');
        }
        $oQuestion = Question::model()->findByPk($qid);
        if (empty($oQuestion)) {
            throw new CHttpException(404, gT("Invalid question id"));
        }
        /* Test the surveyid from question, not from submitted value */
        $surveyid = $oQuestion->sid;
        if (!Permission::model()->hasSurveyPermission($surveyid, 'surveycontent', 'delete')) {
            throw new CHttpException(403, gT("You are not authorized to delete questions."));
        }
        if (!Yii::app()->getRequest()->isPostRequest) {
            throw new CHttpException(405, gT("Invalid action"));
        }

		if (empty($redirectTo)) {
			$redirectTo = Yii::app()->getRequest()->getPost('redirectTo', 'questionlist');
		}
		if ($redirectTo == 'groupoverview') {
			$redirect = Yii::app()->createUrl(
				'questionGroupsAdministration/view/',
				[
					'surveyid' => $surveyid,
					'gid' => $oQuestion->gid,
					'landOnSideMenuTab' => 'structure'
				]
			);
		} else {
			$redirect = Yii::app()->createUrl(
				'surveyAdministration/listQuestions/',
				[
					'surveyid' => $surveyid,
					'landOnSideMenuTab' => 'settings'
				]
			);
		}


        LimeExpressionManager::RevertUpgradeConditionsToRelevance(null, $qid);

        // Check if any other questions have conditions which rely on this question. Don't delete if there are.
        // TMSW Condition->Relevance:  Allow such deletes - can warn about missing relevance separately.
        $oConditions = Condition::model()->findAllByAttributes(['cqid' => $qid]);
        $iConditionsCount = count($oConditions);
        // There are conditions dependent on this question
        if ($iConditionsCount) {
            $sMessage = gT("Question could not be deleted. There are conditions for other questions that rely on this question. You cannot delete this question until those conditions are removed.");
            Yii::app()->setFlashMessage($sMessage, 'error');
            $this->redirect($redirect);
            $this->redirect(['questionAdministration/listquestions/surveyid/' . $surveyid]);
        } else {
            QuestionL10n::model()->deleteAllByAttributes(['qid' => $qid]);
            $result = $oQuestion->delete();
            $sMessage = gT("Question was successfully deleted.");
        }

        if ($massAction) {
            return [
                'message' => $sMessage,
                'status'  => $result
            ];
        }
        if (Yii::app()->request->isAjaxRequest) {
            $this->renderJSON(
                [
                    'status'   => true,
                    'message'  => $sMessage,
                    'redirect' => $redirect
                ]
            );
        }
        Yii::app()->session['flashmessage'] = $sMessage;
        $this->redirect($redirect);
    }

    /**
     * Change the question group/order position of multiple questions
     *
     * @throws CException
     */
    public function actionSetMultipleQuestionGroup()
    {
        $aQids = json_decode(Yii::app()->request->getPost('sItems')); // List of question ids to update
        // New Group ID  (can be same group for a simple position change)
        $iGid = Yii::app()->request->getPost('group_gid');
        $iQuestionOrder = Yii::app()->request->getPost('questionposition'); // Wanted position

        $oQuestionGroup = QuestionGroup::model()->find('gid=:gid', [':gid' => $iGid]); // The New Group object
        $oSurvey = $oQuestionGroup->survey; // The Survey associated with this group

        if (Permission::model()->hasSurveyPermission($oSurvey->sid, 'surveycontent', 'update')) {
            // If survey is active it should not be possible to update
            if ($oSurvey->active == 'N') {
                if ($iQuestionOrder == "") {
                    // If asked "at the end"
                    $iQuestionOrder = (getMaxQuestionOrder($oQuestionGroup->gid));
                }
                self::changeMultipleQuestionPositionAndGroup($aQids, $iQuestionOrder, $oQuestionGroup);
            }
        }
    }

    /**
     * Change the Questions mandatory state
     */
    public function actionChangeMultipleQuestionMandatoryState()
    {
        $aQids = json_decode(Yii::app()->request->getPost('sItems')); // List of question ids to update
        $iSid = (int)Yii::app()->request->getPost('sid');
        $sMandatory = Yii::app()->request->getPost('mandatory', 'N');

        if (Permission::model()->hasSurveyPermission($iSid, 'surveycontent', 'update')) {
            self::setMultipleQuestionMandatoryState($aQids, $sMandatory, $iSid);
        }
    }

    /**
     * Change the "other" option for applicable question types
     */
    public function actionChangeMultipleQuestionOtherState()
    {
        $aQids = json_decode(Yii::app()->request->getPost('sItems')); // List of question ids to update
        $iSid = (int)Yii::app()->request->getPost('sid');
        $sOther = (Yii::app()->request->getPost('other') === 'true') ? 'Y' : 'N';

        if (Permission::model()->hasSurveyPermission($iSid, 'surveycontent', 'update')) {
            self::setMultipleQuestionOtherState($aQids, $sOther, $iSid);
        }
    }

    /**
     * Change attributes for multiple questions
     * ajax request (this is a massive action for questionlists view)
     *
     */
    public function actionChangeMultipleQuestionAttributes()
    {
        $aQidsAndLang        = json_decode($_POST['sItems']); // List of question ids to update
        $iSid                = Yii::app()->request->getPost('sid'); // The survey (for permission check)
        $aAttributesToUpdate = json_decode($_POST['aAttributesToUpdate']); // The list of attributes to updates
        // TODO 1591979134468: this should be get from the question model
        $aValidQuestionTypes = str_split($_POST['aValidQuestionTypes']); //The valid question types for those attributes

        // Calling th model
        QuestionAttribute::model()->setMultiple($iSid, $aQidsAndLang, $aAttributesToUpdate, $aValidQuestionTypes);
    }

    /**
     * Loads the possible Positions where a Question could be inserted to
     *
     * @param $gid
     * @param string $classes
     * @return CWidget|mixed|void
     * @throws Exception
     */
    public function actionAjaxLoadPositionWidget($gid, $classes = '')
    {
        $oQuestionGroup = QuestionGroup::model()->find('gid=:gid', [':gid' =>$gid]);
        if (is_a($oQuestionGroup, 'QuestionGroup') &&
            Permission::model()->hasSurveyPermission($oQuestionGroup->sid, 'surveycontent', 'read')) {
            $aOptions = [
                'display'           => 'form_group',
                'oQuestionGroup'    => $oQuestionGroup,

            ];

            // TODO: Better solution: Hard-code allowed CSS classes.
            if ($classes != '' && $this->isValidCSSClass($classes)) {
                $aOptions['classes'] = $classes;
            }

            return App()->getController()->widget(
                'ext.admin.survey.question.PositionWidget.PositionWidget',
                $aOptions
            );
        }
        return;
    }

    /**
     * render selected items for massive action widget
     * @throws CException
     */

    public function actionRenderItemsSelected()
    {
        $aQids = json_decode(Yii::app()->request->getPost('$oCheckedItems'));
        $aResults     = [];
        $tableLabels  = [gT('Question ID'),gT('Question title') ,gT('Status')];

        foreach ($aQids as $sQid) {
            $iQid        = (int)$sQid;
            $oQuestion      = Question::model()->with('questionl10ns')->findByPk($iQid);
            $oSurvey        = Survey::model()->findByPk($oQuestion->sid);
            $sBaseLanguage  = $oSurvey->language;

            if (is_object($oQuestion)) {
                $aResults[$iQid]['title'] = substr(
                    viewHelper::flatEllipsizeText(
                        $oQuestion->questionl10ns[$sBaseLanguage]->question,
                        true,
                        0
                    ),
                    0,
                    100
                );
                $aResults[$iQid]['result'] = 'selected';
            }
        }

        $this->renderPartial(
            'ext.admin.grid.MassiveActionsWidget.views._selected_items',
            [
                'aResults'     =>  $aResults,
                'successLabel' =>  gT('Selected'),
                'tableLabels'  =>  $tableLabels
            ]
        );
    }

    /**
     * Get HTML for general settings.
     * Called with Ajax after question type is selected.
     *
     * @param int $surveyId
     * @param string $questionType One-char string
     * @param int $questionId Null or 0 if new question is being created.
     * @return void
     */
    public function actionGetGeneralSettingsHTML(int $surveyId, string $questionType, $questionId = null)
    {
        if (empty($questionType)) {
            throw new CHttpException(405, 'Internal error: No question type');
        }
        // TODO: Difference between create and update permissions?
        if (!Permission::model()->hasSurveyPermission($surveyId, 'surveycontent', 'update')) {
            throw new CHttpException(403, gT('No permission'));
        }
        // NB: This works even when $questionId is null (get default question values).
        $question = $this->getQuestionObject($questionId, $questionType);
        if ($questionId) {
            // NB: Could happen if user manipulates request.
            if (!Permission::model()->hasSurveyPermission($question->sid, 'surveycontent', 'update')) {
                throw new CHttpException(403, gT('No permission'));
            }
        }
        $generalSettings = $this->getGeneralOptions(
            $question->qid,
            $questionType,
            $question->gid,
            'core'  // TODO: question_template
        );
        $this->renderPartial("generalSettings", ['generalSettings'  => $generalSettings]);
    }

    /**
     * Copies a question
     *
     * @return void
     */
    public function actionCopyQuestion()
    {
        $aData = [];
        //load helpers
        Yii::app()->loadHelper('surveytranslator');
        Yii::app()->loadHelper('admin.htmleditor');

        //get params from request
        $surveyId = (int)Yii::app()->request->getParam('surveyId');
        $questionGroupId = (int)Yii::app()->request->getParam('questionGroupId');
        $questionIdToCopy = (int)Yii::app()->request->getParam('questionId');

        //permission check ...
        if (!Permission::model()->hasSurveyPermission($surveyId, 'surveycontent', 'create')) {
            Yii::app()->user->setFlash('error', gT("Access denied! You don't have permission to copy a question"));
            $this->redirect(Yii::app()->request->urlReferrer);
        }

        $oQuestion = Question::model()->findByAttributes([
            'sid' => $surveyId,
            'gid' => $questionGroupId,
            'qid' => $questionIdToCopy
        ]);
        if ($oQuestion === null) {
            Yii::app()->user->setFlash('error', gT("Question does not exist."));
            $this->redirect(Yii::app()->request->urlReferrer);
        }

        $aData['surveyid'] = $surveyId; //this is important to load the correct layout (see beforeRender)
        $aData['sid'] = $surveyId; //important for renderGeneraltopbar()
        $aData['gid'] = $questionGroupId; //important for renderGeneraltopbar()
        $aData['qid'] = $questionIdToCopy; //important for renderGeneraltopbar()

        $oSurvey = Survey::model()->findByPk($surveyId);
        $aData['oSurvey'] = $oSurvey;
        $oQuestionGroup = QuestionGroup::model()->find('gid=:gid', array(':gid' => $questionGroupId));
        $aData['oQuestionGroup'] = $oQuestionGroup;
        $aData['oQuestion'] = $oQuestion;

        //array elements for frontend (topbar etc.)
        $aData['sidemenu']['landOnSideMenuTab'] = 'structure';
        $aData['topBar']['showSaveButton'] = true;
        $aData['title_bar']['title'] = $oSurvey->currentLanguageSettings->surveyls_title
            . " (" . gT("ID") . ":" . $surveyId . ")";

        //save the copy ...savecopy (submitbtn pressed ...)
        $savePressed = Yii::app()->request->getParam('savecopy');
        if (isset($savePressed) && $savePressed !== null) {
            $copyQuestionValues = new \LimeSurvey\Datavalueobjects\CopyQuestionValues();
            $copyQuestionValues->setOSurvey($oSurvey);
            $copyQuestionValues->setQuestionCode(Yii::app()->request->getParam('title'));
            $copyQuestionValues->setQuestionGroupId((int)Yii::app()->request->getParam('gid'));
            $copyQuestionValues->setQuestiontoCopy($oQuestion);
            $questionPosition = Yii::app()->request->getParam('questionposition');
            if ($questionPosition==='') { //this means "at the end"
                $questionPosition = -1; //integer indicator for "end"
            }
            //first ensure that all questions for the group have a question_order>0 and possibly set to this state
            Question::setQuestionOrderForGroup($questionGroupId);
            switch ((int)$questionPosition) {
                case -1: //at the end
                    $newQuestionPosition = Question::getHighestQuestionOrderNumberInGroup($questionGroupId) +1;
                    break;
                case 0: //at beginning
                    //set all existing order numbers to +1, and the copied question to order number 1
                    Question::increaseAllOrderNumbersForGroup($questionGroupId);
                    $newQuestionPosition = 1;
                    break;
                default: //all other cases means after question X (the value coming from frontend is already correct)
                    $newQuestionPosition = $questionPosition;
            }
            $copyQuestionValues->setQuestionPositionInGroup($newQuestionPosition);

            $copyQuestionService = new \LimeSurvey\Models\Services\CopyQuestion($copyQuestionValues);
            $copyOptions['copySubquestions'] = (int)Yii::app()->request->getParam('copysubquestions') === 1;
            $copyOptions['copyAnswerOptions'] = (int)Yii::app()->request->getParam('copyanswers') === 1;
            $copyOptions['copyDefaultAnswers'] = (int)Yii::app()->request->getParam('copydefaultanswers') === 1;
            $copyOptions['copySettings'] = (int)Yii::app()->request->getParam('copyattributes') === 1;
            if ($copyQuestionService->copyQuestion($copyOptions)) {
                App()->user->setFlash('success', gT("Saved copied question"));
                $newQuestion = $copyQuestionService->getNewCopiedQuestion();
                $this->redirect(
                    $this->createUrl('questionAdministration/view/',
                        array(
                            'surveyid' => $surveyId,
                            'gid' => $newQuestion->gid,
                            'qid' => $newQuestion->qid
                        )
                    )
                );
                //todo: positions of questions in sidemenu list are not orderd correctly (what has to be done here???)
            } else {
                App()->user->setFlash('error', gT("Could not save copied question"));
            }
        }

        $this->aData = $aData;
        $this->render('copyQuestionForm', $aData);
    }

    /**
     * Get HTML for advanced settings.
     * Called with Ajax after question type is selected.
     *
     * @param int $surveyId
     * @param string $questionType One-char string
     * @param int $questionId Null or 0 if new question is being created.
     * @return void
     */
    public function actionGetAdvancedSettingsHTML(int $surveyId, string $questionType, $questionId = null)
    {
        if (empty($questionType)) {
            throw new CHttpException(405, 'Internal error: No question type');
        }
        // TODO: Difference between create and update permissions?
        if (!Permission::model()->hasSurveyPermission($surveyId, 'surveycontent', 'update')) {
            throw new CHttpException(403, gT('No permission'));
        }
        Yii::app()->loadHelper("admin.htmleditor");
        // NB: This works even when $questionId is null (get default question values).
        $question = $this->getQuestionObject($questionId, $questionType);
        if ($questionId) {
            // NB: Could happen if user manipulates request.
            if (!Permission::model()->hasSurveyPermission($question->sid, 'surveycontent', 'update')) {
                throw new CHttpException(403, gT('No permission'));
            }
        }
        $advancedSettings = $this->getAdvancedOptions(
            $question->qid,
            $questionType,
            'core'  // TODO: question_template
        );
        $this->renderPartial(
            "advancedSettings",
            [
                'advancedSettings'  => $advancedSettings,
                'oQuestion'         => $question,
                'oSurvey'           => $question->survey,
            ]
        );
    }

    /** ++++++++++++  TODO: The following functions should be moved to model or a service class ++++++++++++++++++++++++++ */

    /**
     * Try to get the get-parameter from request.
     * At the moment there are three namings for a survey id:
     * 'sid'
     * 'surveyid'
     * 'iSurveyID'
     *
     * Returns the id as integer or null if not exists any of them.
     *
     * @return int | null
     *
     * @todo While refactoring (at some point) this function should be removed and only one unique identifier should be used
     */
    private function getSurveyIdFromGetRequest(){
        $surveyId = Yii::app()->request->getParam('sid');
        if($surveyId === null){
            $surveyId = Yii::app()->request->getParam('surveyid');
        }
        if($surveyId === null){
            $surveyId = Yii::app()->request->getParam('iSurveyID');
        }

        return (int) $surveyId;
    }

    /**
     * Returns true if $class is a valid CSS class (alphanumeric + '-' and '_')
     *
     * @param string $class
     * @return bool
     */
    protected function isValidCSSClass($class)
    {
        $class = str_replace(['-', '_'], '', $class);
        return ctype_alnum($class);
    }

    /**
     * Set the other state for selected Questions
     *
     * @param array $aQids All question id's affected
     * @param string $sOther the "other" value 'Y' or 'N'
     * @param int $iSid survey id
     */
    public static function setMultipleQuestionOtherState($aQids, $sOther, $iSid)
    {
        foreach ($aQids as $sQid) {
            $iQid = (int)$sQid;
            $oQuestion = Question::model()->findByPk(["qid" => $iQid], ['sid=:sid'], [':sid' => $iSid]);
            // Only set the other state for question types that have this attribute
            if (($oQuestion->type == Question::QT_L_LIST_DROPDOWN)
                || ($oQuestion->type == Question::QT_EXCLAMATION_LIST_DROPDOWN)
                || ($oQuestion->type == Question::QT_P_MULTIPLE_CHOICE_WITH_COMMENTS)
                || ($oQuestion->type == Question::QT_M_MULTIPLE_CHOICE)) {
                $oQuestion->other = $sOther;
                $oQuestion->save();
            }
        }
    }

    /**
     * Set the mandatory state for selected Questions
     *
     * @param array $aQids All question id's affected
     * @param string $sMandatory The mandatory va
     * @param int $iSid survey id
     */
    public static function setMultipleQuestionMandatoryState($aQids, $sMandatory, $iSid)
    {
        foreach ($aQids as $sQid) {
            $iQid = (int)$sQid;
            $oQuestion = Question::model()->findByPk(["qid" => $iQid], ['sid=:sid'], [':sid' => $iSid]);
            // These are the questions types that have no mandatory property - so ignore them
            if ($oQuestion->type != Question::QT_X_BOILERPLATE_QUESTION && $oQuestion->type != Question::QT_VERTICAL_FILE_UPLOAD) {
                $oQuestion->mandatory = $sMandatory;
                $oQuestion->save();
            }
        }
    }

    /**
     * Change the question group/order position of multiple questions
     *
     * @param array $aQids all question id's affected
     * @param int $iQuestionOrder the desired position
     * @param QuestionGroup $oQuestionGroup the desired QuestionGroup
     * @throws CException
     */
    public static function changeMultipleQuestionPositionAndGroup($aQids, $iQuestionOrder, $oQuestionGroup)
    {
        $oTransaction = Yii::app()->db->beginTransaction();
        try {
            // Now, we push each question to the new question group
            // And update positions
            foreach ($aQids as $sQid) {
                // Question basic infos
                $iQid = (int)$sQid;
                $oQuestion = Question::model()->findByAttributes(['qid' => $iQid]); // Question object
                $oldGid = $oQuestion->gid; // The current GID of the question
                $oldOrder = $oQuestion->question_order; // Its current order

                // First, we update all the positions of the questions in the current group of the question
                // If they were after the question, we must decrease by one their position
                Question::model()->updateCounters(
                    ['question_order' => -1],
                    [
                        'condition' => 'gid=:gid AND question_order>=:order',
                        'params'    => [':gid' => $oldGid, ':order' => $oldOrder]
                    ]
                );

                // Then, we must update all the position of the question in the new group of the question
                // If they will be after the question, we must increase their position
                Question::model()->updateCounters(
                    ['question_order' => 1],
                    [
                        'condition' => 'gid=:gid AND question_order>=:order',
                        'params'    => [':gid' => $oQuestionGroup->gid, ':order' => $iQuestionOrder]
                    ]
                );

                // Then we move all the questions with the request QID (same question in different langagues)
                // to the new group, with the righ postion
                Question::model()->updateAll(
                    ['question_order' => $iQuestionOrder, 'gid' => $oQuestionGroup->gid],
                    'qid=:qid',
                    [':qid' => $iQid]
                );
                // Then we update its subquestions
                Question::model()->updateAll(
                    ['gid' => $oQuestionGroup->gid],
                    'parent_qid=:parent_qid',
                    [':parent_qid' => $iQid]
                );

                $iQuestionOrder++;
            }
            $oTransaction->commit();
        } catch (Exception $e) {
            $oTransaction->rollback();
        }
    }

    /**
     * @param int $iSurveyID
     * @param int $gid
     * @param int $qid
     * @return array Array with defaultValues
     */
    public static function getDefaultValues(int $iSurveyID, int $gid, int $qid)
    {
        $aDefaultValues = [];
        $oQuestion = Question::model()->findByAttributes(['qid' => $qid, 'gid' => $gid,]);
        $aQuestionAttributes = $oQuestion->attributes;
        $aQuestionTypeMetadata = QuestionType::modelsAttributes();
        $oSurvey = Survey::model()->findByPk($iSurveyID);

        foreach ($oSurvey->allLanguages as $language) {
            $aDefaultValues[$language] = [];
            $aDefaultValues[$language][$aQuestionAttributes['type']] = [];

            // If there are answerscales
            if ($aQuestionTypeMetadata[$aQuestionAttributes['type']]['answerscales'] > 0) {
                for ($scale_id = 0; $scale_id < $aQuestionTypeMetadata[$aQuestionAttributes['type']]['answerscales']; $scale_id++) {
                    $aDefaultValues[$language][$aQuestionAttributes['type']][$scale_id] = [];

                    $defaultvalue = DefaultValue::model()->with('defaultvaluel10ns')->find(
                        'specialtype = :specialtype AND qid = :qid AND scale_id = :scale_id AND defaultvaluel10ns.language =:language',
                        [
                            ':specialtype' => '',
                            ':qid'         => $qid,
                            ':scale_id'    => $scale_id,
                            ':language'    => $language,
                        ]
                    );
                    $defaultvalue = !empty($defaultvalue->defaultvaluel10ns) && array_key_exists(
                        $language,
                        $defaultvalue->defaultvaluel10ns
                    ) ? $defaultvalue->defaultvaluel10ns[$language]->defaultvalue : null;
                    $aDefaultValues[$language][$aQuestionAttributes['type']][$scale_id]['defaultvalue'] = $defaultvalue;

                    $answerresult = Answer::model()->with('answerl10ns')->findAll(
                        'qid = :qid AND answerl10ns.language = :language',
                        [
                            ':qid'      => $qid,
                            ':language' => $language
                        ]
                    );
                    $aDefaultValues[$language][$aQuestionAttributes['type']][$scale_id]['answers'] = $answerresult;

                    if ($aQuestionAttributes['other'] === 'Y') {
                        $defaultvalue = DefaultValue::model()->with('defaultvaluel10ns')->find(
                            'specialtype = :specialtype AND qid = :qid AND scale_id = :scale_id AND defaultvaluel10ns.language =:language',
                            [
                                ':specialtype' => 'other',
                                ':qid'         => $qid,
                                ':scale_id'    => $scale_id,
                                ':language'    => $language,
                            ]
                        );
                        $defaultvalue = !empty($defaultvalue->defaultvaluel10ns) && array_key_exists(
                            $language,
                            $defaultvalue->defaultvaluel10ns
                        ) ? $defaultvalue->defaultvaluel10ns[$language]->defaultvalue : null;
                        $aDefaultValues[$language][$aQuestionAttributes['type']]['Ydefaultvalue'] = $defaultvalue;
                    }
                }
            }

            // If there are subquestions and no answerscales
            if ($aQuestionTypeMetadata[$aQuestionAttributes['type']]['answerscales'] == 0 &&
                $aQuestionTypeMetadata[$aQuestionAttributes['type']]['subquestions'] > 0) {
                for ($scale_id = 0; $scale_id < $aQuestionTypeMetadata[$aQuestionAttributes['type']]['subquestions']; $scale_id++) {
                    $aDefaultValues[$language][$aQuestionAttributes['type']][$scale_id] = [];

                    $sqresult = Question::model()
                        ->with('questionl10ns')
                        ->findAll(
                            'sid = :sid AND gid = :gid AND parent_qid = :parent_qid AND scale_id = :scale_id AND questionl10ns.language =:language',
                            [
                                ':sid'        => $iSurveyID,
                                ':gid'        => $gid,
                                ':parent_qid' => $qid,
                                ':scale_id'   => 0,
                                ':language'   => $language
                            ]
                        );

                    $aDefaultValues[$language][$aQuestionAttributes['type']][$scale_id]['sqresult'] = [];

                    $options = [];
                    if ($aQuestionAttributes['type'] == Question::QT_M_MULTIPLE_CHOICE || $aQuestionAttributes['type'] == Question::QT_P_MULTIPLE_CHOICE_WITH_COMMENTS) {
                        $options = ['' => gT('<No default value>'), 'Y' => gT('Checked')];
                    }

                    foreach ($sqresult as $aSubquestion) {
                        $defaultvalue = DefaultValue::model()
                            ->with('defaultvaluel10ns')
                            ->find(
                                'specialtype = :specialtype AND qid = :qid AND sqid = :sqid AND scale_id = :scale_id AND defaultvaluel10ns.language =:language',
                                [
                                    ':specialtype' => '',
                                    ':qid'         => $qid,
                                    ':sqid'        => $aSubquestion['qid'],
                                    ':scale_id'    => $scale_id,
                                    ':language'    => $language
                                ]
                            );
                        $defaultvalue = !empty($defaultvalue->defaultvaluel10ns) && array_key_exists(
                            $language,
                            $defaultvalue->defaultvaluel10ns
                        ) ? $defaultvalue->defaultvaluel10ns[$language]->defaultvalue : null;

                        $question = $aSubquestion->questionl10ns[$language]->question;
                        $aSubquestion = $aSubquestion->attributes;
                        $aSubquestion['question'] = $question;
                        $aSubquestion['defaultvalue'] = $defaultvalue;
                        $aSubquestion['options'] = $options;

                        $aDefaultValues[$language][$aQuestionAttributes['type']][$scale_id]['sqresult'][] = $aSubquestion;
                    }
                }
            }
            if ($aQuestionTypeMetadata[$aQuestionAttributes['type']]['answerscales'] == 0 &&
                $aQuestionTypeMetadata[$aQuestionAttributes['type']]['subquestions'] == 0) {
                $defaultvalue = DefaultValue::model()
                    ->with('defaultvaluel10ns')
                    ->find(
                        'specialtype = :specialtype AND qid = :qid AND scale_id = :scale_id AND defaultvaluel10ns.language =:language',
                        [
                            ':specialtype' => '',
                            ':qid'         => $qid,
                            ':scale_id'    => 0,
                            ':language'    => $language,
                        ]
                    );
                $aDefaultValues[$language][$aQuestionAttributes['type']][0] = !empty($defaultvalue->defaultvaluel10ns) && array_key_exists(
                    $language,
                    $defaultvalue->defaultvaluel10ns
                ) ? $defaultvalue->defaultvaluel10ns[$language]->defaultvalue : null;
            }
        }

        return $aDefaultValues;
    }

    /**
     * Creates a question object
     * This is either an instance of the placeholder model QuestionCreate for new questions,
     * or of Question for already existing ones
     *
     * todo: this should be moved to model ...
     *
     * @param int $iQuestionId
     * @param string $sQuestionType
     * @param int $gid
     * @return Question
     * @throws CException
     */
    private function getQuestionObject($iQuestionId = null, $sQuestionType = null, $gid = null)
    {
        //todo: this should be done in the action directly
        $iSurveyId = App()->request->getParam('sid') ??
            App()->request->getParam('surveyid') ??
            App()->request->getParam('surveyId');
        //$oQuestion = Question::model()->findByPk($iQuestionId);
        $oQuestion = Question::model()->find(
            'sid = :sid AND qid = :qid',
            [':sid' => $iSurveyId, ':qid' => (int) $iQuestionId]
        );

        if ($oQuestion == null) {
            $oQuestion = QuestionCreate::getInstance($iSurveyId, $sQuestionType);
        }

        if ($sQuestionType != null) {
            $oQuestion->type = $sQuestionType;
        }

        if ($gid != null) {
            $oQuestion->gid = $gid;
        }

        return $oQuestion;
    }

    /**
     * @todo document me
     *
     * @param int $iQuestionId
     * @param string $sQuestionType
     * @param int $gid
     * @param string $question_template
     *
     * @return void|array
     * @throws CException
     */
    private function getGeneralOptions(
        $iQuestionId = null,
        $sQuestionType = null,
        $gid = null,
        $question_template = 'core'
    ) {
        $oQuestion = $this->getQuestionObject($iQuestionId, $sQuestionType, $gid);
        $result = $oQuestion
            ->getDataSetObject()
            ->getGeneralSettingsArray($oQuestion->qid, $sQuestionType, null, $question_template);
        return $result;
    }

    /**
     * @todo document me.
     * @todo move this function somewhere else, this should not be part of controller ... (e.g. model)
     *
     * @param Question $oQuestion
     * @return array
     */
    private function getCompiledQuestionData(&$oQuestion)
    {
        LimeExpressionManager::StartProcessingPage(false, true);
        $aQuestionDefinition = array_merge($oQuestion->attributes, ['typeInformation' => $oQuestion->questionType]);
        $oQuestionGroup = QuestionGroup::model()->findByPk($oQuestion->gid);
        $aQuestionGroupDefinition = array_merge($oQuestionGroup->attributes, $oQuestionGroup->questiongroupl10ns);

        $aScaledSubquestions = $oQuestion->getOrderedSubQuestions();
        foreach ($aScaledSubquestions as $scaleId => $aSubquestions) {
            $aScaledSubquestions[$scaleId] = array_map(
                function ($oSubQuestion) {
                    return array_merge($oSubQuestion->attributes, $oSubQuestion->questionl10ns);
                },
                $aSubquestions
            );
        }

        $aScaledAnswerOptions = $oQuestion->getOrderedAnswers();
        foreach ($aScaledAnswerOptions as $scaleId => $aAnswerOptions) {
            $aScaledAnswerOptions[$scaleId] = array_map(
                function ($oAnswerOption) {
                    return array_merge($oAnswerOption->attributes, $oAnswerOption->answerl10ns);
                },
                $aAnswerOptions
            );
        }
        $aReplacementData = [];
        $questioni10N = [];
        foreach ($oQuestion->questionl10ns as $lng => $oQuestionI10N) {
            $questioni10N[$lng] = $oQuestionI10N->attributes;

            templatereplace(
                $oQuestionI10N->question,
                [],
                $aReplacementData,
                'Unspecified',
                false,
                $oQuestion->qid
            );

            $questioni10N[$lng]['question_expression'] = viewHelper::stripTagsEM(
                LimeExpressionManager::GetLastPrettyPrintExpression()
            );

            templatereplace($oQuestionI10N->help, [], $aReplacementData, 'Unspecified', false, $oQuestion->qid);
            $questioni10N[$lng]['help_expression'] = viewHelper::stripTagsEM(
                LimeExpressionManager::GetLastPrettyPrintExpression()
            );
        }
        LimeExpressionManager::FinishProcessingPage();
        return [
            'question'      => $aQuestionDefinition,
            'questiongroup' => $aQuestionGroupDefinition,
            'i10n'          => $questioni10N,
            'subquestions'  => $aScaledSubquestions,
            'answerOptions' => $aScaledAnswerOptions,
        ];
    }

    /**
     * It returns a preformatted array of advanced settings.
     *
     * @param int $iQuestionId
     * @param string $sQuestionType
     * @param string $question_template
     * @return array
     * @throws CException
     * @throws Exception
     */
    private function getAdvancedOptions($iQuestionId = null, $sQuestionType = null, $question_template = 'core')
    {
        //here we get a Question object (also if question is new --> QuestionCreate)
        $oQuestion = $this->getQuestionObject($iQuestionId, $sQuestionType);
        $advancedSettings = $oQuestion->getAdvancedSettingsWithValuesByCategory(null);
        // TODO: Why can empty array be saved as value?
        foreach ($advancedSettings as &$category) {
            foreach ($category as &$setting) {
                if ($setting['value'] === []) {
                    $setting['value'] = null;
                }
            }
        }
        // This category is "general setting".
        unset($advancedSettings['Attribute']);
        return $advancedSettings;
    }

    /**
     *
     * todo: this should be moved to model, not a controller function ...
     *
     * @param $oQuestion
     * @return array
     */
    private function getCompiledSurveyInfo($oQuestion)
    {
        $oSurvey = $oQuestion->survey;
        $aQuestionTitles = $oCommand = Yii::app()->db->createCommand()
            ->select('title')
            ->from('{{questions}}')
            ->where('sid=:sid and parent_qid=0')
            ->queryColumn([':sid' => $oSurvey->sid]);
        $isActive = $oSurvey->isActive;
        $questionCount = safecount($aQuestionTitles);
        $groupCount = safecount($oSurvey->groups);

        return [
            "aQuestionTitles" => $aQuestionTitles,
            "isActive"        => $isActive,
            "questionCount"   => $questionCount,
            "groupCount"      => $groupCount,
        ];
    }

    /**
     * Method to store and filter questionData for a new question
     *
     * todo: move to model or service class
     *
     * @param array $aQuestionData what is inside this array ??
     * @param boolean $subquestion
     * @return Question
     * @throws CHttpException
     */
    private function storeNewQuestionData($aQuestionData = null, $subquestion = false)
    {
        $iSurveyId = $aQuestionData['sid'];
        $oSurvey = Survey::model()->findByPk($iSurveyId);
        // TODO: Don't read request from private methods.
        $iQuestionGroupId = (int) App()->request->getParam('gid'); //the group id the question belongs to
        $type = SettingsUser::getUserSettingValue(
            'preselectquestiontype',
            null,
            null,
            null,
            App()->getConfig('preselectquestiontype')
        );

        if (isset($aQuestionData['same_default'])) {
            if ($aQuestionData['same_default'] == 1) {
                $aQuestionData['same_default'] = 0;
            } else {
                $aQuestionData['same_default'] = 1;
            }
        }

        $aQuestionData = array_merge(
            [
                'sid'        => $iSurveyId,
                'gid'        => $iQuestionGroupId,
                'type'       => $type,
                'other'      => 'N',
                'mandatory'  => 'N',
                'relevance'  => 1,
                'group_name' => '',
                'modulename' => '',
                'encrypted'  => 'N'
            ],
            $aQuestionData
        );
        unset($aQuestionData['qid']);

        if ($subquestion) {
            foreach ($oSurvey->allLanguages as $sLanguage) {
                unset($aQuestionData[$sLanguage]);
            }
        } else {
            $aQuestionData['question_order'] = getMaxQuestionOrder($iQuestionGroupId);
        }

        $oQuestion = new Question();
        $oQuestion->setAttributes($aQuestionData, false);

        //set the question_order the highest existing number +1, if no question exists for the group
        //set the question_order to 1
        $highestOrderNumber = Question::getHighestQuestionOrderNumberInGroup($iQuestionGroupId);
        if ($highestOrderNumber === null) { //this means there is no question inside this group ...
            $oQuestion->question_order = Question::START_SORTING_VALUE;
        } else {
            $oQuestion->question_order = $highestOrderNumber +1;
        }


        if ($oQuestion == null) {
            throw new LSJsonException(
                500,
                gT("Question creation failed - input was malformed or invalid"),
                0,
                null,
                true
            );
        }

        $saved = $oQuestion->save();
        if ($saved == false) {
            throw new LSJsonException(
                500,
                "Object creation failed, couldn't save.\n ERRORS:\n"
                . print_r($oQuestion->getErrors(), true),
                0,
                null,
                true
            );
        }

        $i10N = [];
        foreach ($oSurvey->allLanguages as $sLanguage) {
            $i10N[$sLanguage] = new QuestionL10n();
            $i10N[$sLanguage]->setAttributes(
                [
                    'qid'      => $oQuestion->qid,
                    'language' => $sLanguage,
                    'question' => '',
                    'help'     => '',
                    'script'   => '',
                ],
                false
            );
            $i10N[$sLanguage]->save();
        }

        return $oQuestion;
    }

    /**
     * Method to store and filter questionData for editing a question
     *
     * @param Question $oQuestion
     * @param array $aQuestionData
     * @return Question
     * @throws CHttpException
     */
    private function updateQuestionData(&$oQuestion, $aQuestionData)
    {
        //todo something wrong in frontend ... (?what is wrong?)

        if (isset($aQuestionData['same_default'])) {
            if ($aQuestionData['same_default'] == 1) {
                $aQuestionData['same_default'] = 0;
            } else {
                $aQuestionData['same_default'] = 1;
            }
        }

        $oQuestion->setAttributes($aQuestionData, false);
        if ($oQuestion == null) {
            throw new LSJsonException(
                500,
                gT("Question update failed, input array malformed or invalid"),
                0,
                null,
                true
            );
        }

        $saved = $oQuestion->save();
        if ($saved == false) {
            throw new LSJsonException(
                500,
                "Update failed, could not save. ERRORS:<br/>"
                . implode(", ", $oQuestion->getErrors()['title']),
                0,
                null,
                true
            );
        }
        return $oQuestion;
    }

    /**
     * @todo document me
     *
     * @param Question $oQuestion
     * @param array $dataSet
     * @return boolean
     * @throws CHttpException
     */
    private function applyI10N($oQuestion, $dataSet)
    {
        foreach ($dataSet as $sLanguage => $aI10NBlock) {
            $i10N = QuestionL10n::model()->findByAttributes(['qid' => $oQuestion->qid, 'language' => $sLanguage]);
            if (empty($i10N)) {
                throw new Exception('Found no L10n object');
            }
            $i10N->setAttributes(
                [
                    'question' => $aI10NBlock['question'],
                    'help'     => $aI10NBlock['help'],
                    'script'   => $aI10NBlock['script'],
                ],
                false
            );
            if (!$i10N->save()) {
                throw new CHttpException(500, gT("Could not store translation"));
            }
        }

        return true;
    }

    /**
     * @todo document me
     *
     * @param Question $oQuestion
     * @param array $dataSet
     * @return boolean
     * @throws CHttpException
     */
    private function unparseAndSetGeneralOptions($oQuestion, $dataSet)
    {
        $aQuestionBaseAttributes = $oQuestion->attributes;

        foreach ($dataSet as $sAttributeKey => $attributeValue) {
            if ($sAttributeKey === 'debug' || !isset($attributeValue)) {
                continue;
            }
            if (array_key_exists($sAttributeKey, $aQuestionBaseAttributes)) {
                $oQuestion->$sAttributeKey = $attributeValue;
            } elseif (!QuestionAttribute::model()->setQuestionAttribute(
                $oQuestion->qid,
                $sAttributeKey,
                $attributeValue
            )) {
                throw new CHttpException(500, gT("Could not store general options"));
            }
        }

        if (!$oQuestion->save()) {
            throw new CHttpException(
                500,
                gT("Could not store question after general options") . PHP_EOL
                . print_r($oQuestion->getErrors(), true)
            );
        }

        return true;
    }

    /**
     * @todo document me
     *
     * @param Question $oQuestion
     * @param array $dataSet
     * @return boolean
     * @throws CHttpException
     */
    private function unparseAndSetAdvancedOptions($oQuestion, $dataSet)
    {
        $aQuestionBaseAttributes = $oQuestion->attributes;

        foreach ($dataSet as $sAttributeCategory => $aAttributeCategorySettings) {
            if ($sAttributeCategory === 'debug') {
                continue;
            }
            foreach ($aAttributeCategorySettings as $sAttributeKey => $attributeValue) {
                $newValue = $attributeValue;

                // Set default value if empty.
                // TODO: Default value
                if ($newValue === ""
                    && isset($attributeValue['aFormElementOptions']['default'])) {
                    $newValue = $attributeValue['aFormElementOptions']['default'];
                }

                if (is_array($newValue)) {
                    foreach ($newValue as $lngKey => $content) {
                        if ($lngKey === 'expression') {
                            continue;
                        }
                        if (!QuestionAttribute::model()->setQuestionAttributeWithLanguage(
                            $oQuestion->qid,
                            $sAttributeKey,
                            $content,
                            $lngKey
                        )) {
                            throw new CHttpException(500, gT("Could not store advanced options"));
                        }
                    }
                } elseif (array_key_exists($sAttributeKey, $aQuestionBaseAttributes)) {
                    $oQuestion->$sAttributeKey = $newValue;
                } elseif (!QuestionAttribute::model()->setQuestionAttribute(
                    $oQuestion->qid,
                    $sAttributeKey,
                    $newValue
                )) {
                    throw new CHttpException(500, gT("Could not store advanced options"));
                }
            }
        }

        if (!$oQuestion->save()) {
            throw new CHttpException(500, gT("Could not store advanced options"));
        }

        return true;
    }

    /**
     * Copies the default value(s) set for a question
     *
     * @param Question $oQuestion
     * @param integer $oldQid
     *
     * @return boolean
     * @throws CHttpException
     */
    private function copyDefaultAnswers($oQuestion, $oldQid)
    {
        if (empty($oldQid)) {
            return false;
        }

        $oOldDefaultValues = DefaultValue::model()->with('defaultvaluel10ns')->findAllByAttributes(['qid' => $oldQid]);

        $setApplied['defaultValues'] = array_reduce(
            $oOldDefaultValues,
            function ($collector, $oDefaultValue) use ($oQuestion) {
                $oNewDefaultValue = new DefaultValue();
                $oNewDefaultValue->setAttributes($oDefaultValue->attributes, false);
                $oNewDefaultValue->dvid = null;
                $oNewDefaultValue->qid = $oQuestion->qid;

                if (!$oNewDefaultValue->save()) {
                    throw new CHttpException(
                        500,
                        "Could not save default values. ERRORS:"
                        . print_r($oQuestion->getErrors(), true)
                    );
                }

                foreach ($oDefaultValue->defaultvaluel10ns as $oDefaultValueL10n) {
                    $oNewDefaultValueL10n = new DefaultValueL10n();
                    $oNewDefaultValueL10n->setAttributes($oDefaultValueL10n->attributes, false);
                    $oNewDefaultValueL10n->id = null;
                    $oNewDefaultValueL10n->dvid = $oNewDefaultValue->dvid;
                    if (!$oNewDefaultValueL10n->save()) {
                        throw new CHttpException(
                            500,
                            "Could not save default value I10Ns. ERRORS:"
                            . print_r($oQuestion->getErrors(), true)
                        );
                    }
                }

                return true;
            },
            true
        );
        return true;
    }

    /**
     * @param Question $oQuestion
     * @param array $subquestionsArray Data from request.
     * @return void
     * @throws CHttpException
     */
    private function storeSubquestions($oQuestion, $subquestionsArray)
    {
        $questionOrder = 0;
        foreach ($subquestionsArray as $subquestionId => $subquestionArray) {
            if ($subquestionId == 0 or strpos($subquestionId, 'new') === 0) {
                // New subquestion
            } else {
                // Updating subquestion
            }
            foreach ($subquestionArray as $scaleId => $data) {
                $subquestion = new Question();
                $subquestion->sid        = $oQuestion->sid;
                $subquestion->gid        = $oQuestion->gid;
                $subquestion->parent_qid = $oQuestion->qid;
                $subquestion->question_order = $questionOrder;
                $subquestion->title      = $data['code'];
                $subquestion->relevance  = $data['relevance'];
                if (!$subquestion->save()) {
                    throw new CHttpException(
                        500,
                        gT("Could not save subquestion") . PHP_EOL
                        . print_r($subquestion->getErrors(), true)
                    );
                }
                $subquestion->refresh();
                foreach ($data['subquestionl10n'] as $lang => $questionText) {
                    $l10n = new QuestionL10n();
                    $l10n->qid = $subquestion->qid;
                    $l10n->language = $lang;
                    $l10n->question = $questionText;
                    if (!$l10n->save()) {
                        throw new CHttpException(
                            500,
                            gT("Could not save subquestion") . PHP_EOL
                            . print_r($l10n->getErrors(), true)
                        );
                    }
                }
                /*
                $oSubQuestion = Question::model()->findByPk($aSubquestionDataSet['qid']);
                $oSubQuestion = Question::model()->find(
                    'sid = :sid AND qid = :qid',
                    [':sid' => $oQuestion->sid, ':qid' => (int) $aSubquestionDataSet['qid']]
                );
                if ($oSubQuestion != null && !$isCopyProcess) {
                    $oSubQuestion = $this->updateQuestionData($oSubQuestion, $aSubquestionDataSet);
                } elseif (!$oQuestion->survey->isActive) {
                    $aSubquestionDataSet['parent_qid'] = $oQuestion->qid;
                    $oSubQuestion = $this->storeNewQuestionData($aSubquestionDataSet, true);
                }
                $this->applyI10NSubquestion($oSubQuestion, $aSubquestionDataSet);
                 */
            }
        }
    }

    /**
     * @todo document me
     *
     *
     * @param Question $oQuestion
     * @param array $dataSet
     * @return boolean
     * @throws CHttpException
     */
    private function applyI10NSubquestion($oQuestion, $dataSet)
    {
        foreach ($oQuestion->survey->allLanguages as $sLanguage) {
            $aI10NBlock = $dataSet[$sLanguage];
            $i10N = QuestionL10n::model()->findByAttributes(['qid' => $oQuestion->qid, 'language' => $sLanguage]);
            $i10N->setAttributes(
                [
                    'question' => $aI10NBlock['question'],
                    'help'     => $aI10NBlock['help'],
                ],
                false
            );
            if (!$i10N->save()) {
                throw new CHttpException(500, gT("Could not store translation for subquestion"));
            }
        }

        return true;
    }

    /**
     * @param Question $oQuestion
     * @param array $dataSet
     * @param bool $isCopyProcess
     * @return boolean
     * @throws CHttpException
     * @todo document me
     *
     *
     */
    private function storeAnswerOptions(&$oQuestion, $dataSet, $isCopyProcess = false)
    {
        $this->cleanAnsweroptions($oQuestion, $dataSet);
        foreach ($dataSet as $aAnswerOptions) {
            foreach ($aAnswerOptions as $iScaleId => $aAnswerOptionDataSet) {
                $aAnswerOptionDataSet['sortorder'] = (int)$aAnswerOptionDataSet['sortorder'];
                $oAnswer = Answer::model()->findByPk($aAnswerOptionDataSet['aid']);
                if ($oAnswer == null || $isCopyProcess) {
                    $oAnswer = new Answer();
                    $oAnswer->qid = $oQuestion->qid;
                    unset($aAnswerOptionDataSet['aid']);
                    unset($aAnswerOptionDataSet['qid']);
                }

                $codeIsEmpty = (!isset($aAnswerOptionDataSet['code']));
                if ($codeIsEmpty) {
                    throw new CHttpException(
                        500,
                        "Answer option code cannot be empty"
                    );
                }
                $oAnswer->setAttributes($aAnswerOptionDataSet);
                $answerSaved = $oAnswer->save();
                if (!$answerSaved) {
                    throw new CHttpException(
                        500,
                        "Answer option couldn't be saved. Error: "
                        . print_r($oAnswer->getErrors(), true)
                    );
                }
                $this->applyAnswerI10N($oAnswer, $oQuestion, $aAnswerOptionDataSet);
            }
        }
        return true;
    }

    /**
     * @todo document me
     *
     * @param Question $oQuestion
     * @param array $dataSet
     * @return void
     */
    private function cleanAnsweroptions(&$oQuestion, &$dataSet)
    {
        $aAnsweroptions = $oQuestion->answers;
        array_walk(
            $aAnsweroptions,
            function ($oAnsweroption) use (&$dataSet) {
                $exists = false;
                foreach ($dataSet as $scaleId => $aAnsweroptions) {
                    foreach ($aAnsweroptions as $i => $aAnsweroptionDataSet) {
                        if (((is_numeric($aAnsweroptionDataSet['aid'])
                                    && $oAnsweroption->aid == $aAnsweroptionDataSet['aid'])
                                || $oAnsweroption->code == $aAnsweroptionDataSet['code'])
                            && ($oAnsweroption->scale_id == $scaleId)
                        ) {
                            $exists = true;
                            $dataSet[$scaleId][$i]['aid'] = $oAnsweroption->aid;
                        }

                        if (!$exists) {
                            $oAnsweroption->delete();
                        }
                    }
                }
            }
        );
    }

    /**
     * @todo document me
     *
     * @param Answer $oAnswer
     * @param Question $oQuestion
     * @param array $dataSet
     *
     * @return boolean
     * @throws CHttpException
     */
    private function applyAnswerI10N($oAnswer, $oQuestion, $dataSet)
    {
        foreach ($oQuestion->survey->allLanguages as $sLanguage) {
            $i10N = AnswerL10n::model()->findByAttributes(['aid' => $oAnswer->aid, 'language' => $sLanguage]);
            if ($i10N == null) {
                $i10N = new AnswerL10n();
                $i10N->setAttributes(
                    [
                        'aid'      => $oAnswer->aid,
                        'language' => $sLanguage,
                    ],
                    false
                );
            }
            $i10N->setAttributes(
                [
                    'answer' => $dataSet[$sLanguage]['answer'],
                ],
                false
            );

            if (!$i10N->save()) {
                throw new CHttpException(500, gT("Could not store translation for answer option"));
            }
        }

        return true;
    }

    /**
     * @return array
     */
    private function getQuestionTypeGroups($aQuestionTypeList)
    {
        $aQuestionTypeGroups = [];

        if (App()->session['questionselectormode'] !== 'default') {
            $selectormodeclass = App()->session['questionselectormode'];
        } else {
            $selectormodeclass = App()->getConfig('defaultquestionselectormode');
        }
        uasort($aQuestionTypeList, "questionTitleSort");
        foreach ($aQuestionTypeList as $questionType) {
            $htmlReadyGroup = str_replace(' ', '_', strtolower($questionType['group']));
            if (!isset($aQuestionTypeGroups[$htmlReadyGroup])) {
                $aQuestionTypeGroups[$htmlReadyGroup] = array(
                    'questionGroupName' => $questionType['group']
                );
            }
            $imageName = $questionType['question_type'];
            if ($imageName == ":") {
                $imageName = "COLON";
            } elseif ($imageName == "|") {
                $imageName = "PIPE";
            } elseif ($imageName == "*") {
                $imageName = "EQUATION";
            }
            $questionType['type'] = $questionType['question_type'];
            $questionType['detailpage'] = '
                <div class="col-sm-12 currentImageContainer">
                <img src="' . $questionType['image_path'] . '" />
                </div>';
            if ($imageName == 'S') {
                $questionType['detailpage'] = '
                    <div class="col-sm-12 currentImageContainer">
                    <img src="' . App()->getConfig('imageurl') . '/screenshots/' . $imageName . '.png" />
                    <img src="' . App()->getConfig('imageurl') . '/screenshots/' . $imageName . '2.png" />
                    </div>';
            }
            $aQuestionTypeGroups[$htmlReadyGroup]['questionTypes'][] = $questionType;
        }
        return $aQuestionTypeGroups;
    }
}
