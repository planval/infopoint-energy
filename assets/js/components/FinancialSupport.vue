<template>

    <div class="financial-support-component">

        <div class="financial-support-component-form">

            <div class="financial-support-component-form-header">

                <h3>Eintrag erstellen</h3>

                <div class="financial-support-component-form-header-actions">
                    <a class="button warning" @click="financialSupport.isPublic = true" v-if="!financialSupport.isPublic">Entwurf</a>
                    <a class="button success" @click="financialSupport.isPublic = false" v-if="financialSupport.isPublic">Öffentlich</a>
                    <a @click="locale = 'de'" class="button" :class="{primary: locale === 'de'}">DE</a>
                    <a @click="locale = 'fr'" class="button" :class="{primary: locale === 'fr'}">FR</a>
                    <a @click="locale = 'it'" class="button" :class="{primary: locale === 'it'}">IT</a>
                    <a class="button error" @click="clickDelete()" v-if="financialSupport.id">Löschen</a>
                    <a class="button warning" @click="clickCancel()">Abbrechen</a>
                    <a class="button primary" @click="clickSave()">Speichern</a>
                </div>

            </div>

            <!-- Publication Status Display -->
            <div class="publication-status-section" v-if="financialSupport.id">
                <div class="publication-status-container">
                    <div class="publication-status-item">
                        <span class="publication-status-label">Live:</span>
                        <span class="publication-status-value" :class="publicationStatus.live ? 'published' : 'not-published'">
                            {{ publicationStatus.live ? publicationStatus.live : 'Nicht publiziert' }}
                        </span>
                    </div>
                    <div class="publication-status-item">
                        <span class="publication-status-label">Test:</span>
                        <span class="publication-status-value" :class="publicationStatus.staging ? 'published' : 'not-published'">
                            {{ publicationStatus.staging ? publicationStatus.staging : 'Nicht publiziert' }}
                        </span>
                    </div>
                </div>
            </div>

            <div class="financial-support-component-form-section">

                <div class="row">
                    <div class="col-md-6" v-if="locale === 'de'">
                        <label for="title">Bezeichnung</label>
                        <input id="title" type="text" class="form-control" v-model="financialSupport.name" :placeholder="translateField(financialSupport, 'name', locale)">
                    </div>
                    <div class="col-md-6" v-else>
                        <label for="title">Bezeichnung (Übersetzung {{ locale.toUpperCase() }})</label>
                        <input id="title" type="text" class="form-control" v-model="financialSupport.translations[locale].name" :placeholder="translateField(financialSupport, 'name', locale)">
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-12" v-if="locale === 'de'">
                        <label for="images">Logo</label>
                        <image-selector id="images" :multiple="false" :item="financialSupport.logo" :locale="locale" @changed="financialSupport.logo = $event"></image-selector>
                    </div>
                    <div class="col-md-12" v-else>
                        <label for="images">Logo (Übersetzung {{ locale.toUpperCase() }})</label>
                        <image-selector id="images" :multiple="false" :item="financialSupport.translations[locale].logo" :locale="'de'" @changed="financialSupport.translations[locale].logo = $event"></image-selector>
                    </div>
                </div>


                <div class="row">
                    <div class="col-md-6" v-if="locale === 'de'">
                        <label for="funding_provider">Förderstelle</label>
                        <input id="funding_provider" type="text" class="form-control" v-model="financialSupport.fundingProvider">
                    </div>
                    <div class="col-md-6" v-else>
                        <label for="funding_provider">Förderstelle (Übersetzung {{ locale.toUpperCase() }})</label>
                        <input id="funding_provider" type="text" class="form-control" v-model="financialSupport.translations[locale].fundingProvider" :placeholder="translateField(financialSupport, 'fundingProvider', locale)">
                    </div>
                </div>

                <!-- <div class="row">
                    <div class="col-md-6">
                        <label for="states">Kantone</label>
                        <tag-selector id="states" :model="financialSupport.states"
                                      :options="states.filter(state => !state.context || state.context === 'financial-support')" :searchType="'select'"></tag-selector>
                    </div>
                </div> -->

                <div class="row">
                    <div class="col-md-6">
                        <label for="instruments">Unterstützungsform</label>
                        <tag-selector id="instruments" :model="financialSupport.instruments"
                                      :options="instruments.filter(instrument => !instrument.context || instrument.context === 'financial-support')"
                                      :searchType="'select'"></tag-selector>
                        <div v-if="hasWeitereInstrument" class="mt-2">
                            <label v-if="locale === 'de'">Weitere Unterstützungsform</label>
                            <label v-else>Weitere Unterstützungsform (Übersetzung {{ locale.toUpperCase() }})</label>
                            <input type="text" class="form-control" v-model="currentOtherOptionValues.instrument">
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6">
                        <label for="beneficiaries">Begünstigte</label>
                        <tag-selector id="beneficiaries" :model="financialSupport.beneficiaries"
                                      :options="beneficiaries.filter(beneficiary => !beneficiary.context || beneficiary.context === 'financial-support')"
                                      :searchType="'select'"></tag-selector>
                        <div v-if="hasWeitereBeneficiary" class="mt-2">
                            <label v-if="locale === 'de'">Weitere Begünstigte</label>
                            <label v-else>Weitere Begünstigte (Übersetzung {{ locale.toUpperCase() }})</label>
                            <input type="text" class="form-control" v-model="currentOtherOptionValues.beneficiary">
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-8" v-if="locale === 'de'">
                        <label for="text">Zusammenfassung «Lead»</label>
                        <ckeditor id="text" :editor="editor" :config="editorConfig"
                                  v-model="financialSupport.description" :placeholder="translateField(financialSupport, 'description', locale)"></ckeditor>
                    </div>
                    <div class="col-md-8" v-else>
                        <label for="text">Zusammenfassung «Lead» (Übersetzung {{ locale.toUpperCase() }})</label>
                        <ckeditor id="text" :editor="editor" :config="editorConfig"
                                  v-model="financialSupport.translations[locale].description" :placeholder="translateField(financialSupport, 'description', locale)"></ckeditor>
                    </div>
                </div>



                <div class="row">
                    <div class="col-md-8" v-if="locale === 'de'">
                        <label for="text">Kurzbeschrieb</label>
                        <ckeditor id="text" :editor="editor" :config="editorConfig"
                                  v-model="financialSupport.additionalInformation" :placeholder="translateField(financialSupport, 'additionalInformation', locale)"></ckeditor>
                    </div>
                    <div class="col-md-8" v-else>
                        <label for="text">Kurzbeschrieb (Übersetzung {{ locale.toUpperCase() }})</label>
                        <ckeditor id="text" :editor="editor" :config="editorConfig"
                                  v-model="financialSupport.translations[locale].additionalInformation" :placeholder="translateField(financialSupport, 'additionalInformation', locale)"></ckeditor>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-8" v-if="locale === 'de'">
                        <label for="text">Teilnahmekriterien</label>
                        <ckeditor id="text" :editor="editor" :config="editorConfig"
                                  v-model="financialSupport.inclusionCriteria" :placeholder="translateField(financialSupport, 'inclusionCriteria', locale)"></ckeditor>
                    </div>
                    <div class="col-md-8" v-else>
                        <label for="text">Teilnahmekriterien (Übersetzung {{ locale.toUpperCase() }})</label>
                        <ckeditor id="text" :editor="editor" :config="editorConfig"
                                  v-model="financialSupport.translations[locale].inclusionCriteria" :placeholder="translateField(financialSupport, 'inclusionCriteria', locale)"></ckeditor>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-8" v-if="locale === 'de'">
                        <label for="text">Ausschlusskriterien</label>
                        <ckeditor id="text" :editor="editor" :config="editorConfig"
                                  v-model="financialSupport.exclusionCriteria" :placeholder="translateField(financialSupport, 'exclusionCriteria', locale)"></ckeditor>
                    </div>
                    <div class="col-md-8" v-else>
                        <label for="text">Ausschlusskriterien (Übersetzung {{ locale.toUpperCase() }})</label>
                        <ckeditor id="text" :editor="editor" :config="editorConfig"
                                  v-model="financialSupport.translations[locale].exclusionCriteria" :placeholder="translateField(financialSupport, 'exclusionCriteria', locale)"></ckeditor>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-8" v-if="locale === 'de'">
                        <label for="text">Beantragung</label>
                        <ckeditor id="text" :editor="editor" :config="editorConfig"
                                  v-model="financialSupport.application" :placeholder="translateField(financialSupport, 'application', locale)"></ckeditor>
                    </div>
                    <div class="col-md-8" v-else>
                        <label for="text">Beantragung (Übersetzung {{ locale.toUpperCase() }})</label>
                        <ckeditor id="text" :editor="editor" :config="editorConfig"
                                  v-model="financialSupport.translations[locale].application" :placeholder="translateField(financialSupport, 'application', locale)"></ckeditor>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-8" v-if="locale === 'de'">
                        <label for="text">Tipps für die erfolgreiche Beantragung</label>
                        <ckeditor id="text" :editor="editor" :config="editorConfig"
                                  v-model="financialSupport.applicationTips" :placeholder="translateField(financialSupport, 'applicationTips', locale)"></ckeditor>
                    </div>
                    <div class="col-md-8" v-else>
                        <label for="text">Tipps für die erfolgreiche Beantragung (Übersetzung {{ locale.toUpperCase() }})</label>
                        <ckeditor id="text" :editor="editor" :config="editorConfig"
                                  v-model="financialSupport.translations[locale].applicationTips" :placeholder="translateField(financialSupport, 'applicationTips', locale)"></ckeditor>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6">
                        <label for="topics">Themenschwerpunkt</label>
                        <tag-selector id="topics" :model="financialSupport.topics"
                                      :options="topics.filter(topic => !topic.context || topic.context === 'financial-support')" :searchType="'select'"></tag-selector>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6">
                        <label for="projectTypes">Innovationsphasen</label>
                        <tag-selector id="projectTypes" :model="financialSupport.projectTypes"
                                      :options="projectTypes.filter(projectType => !projectType.context || projectType.context === 'financial-support')" :searchType="'select'"></tag-selector>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-8" v-if="locale === 'de'">
                        <label for="text">Finanzierung</label>
                        <ckeditor id="text" :editor="editor" :config="editorConfig"
                                  v-model="financialSupport.financingRatio" :placeholder="translateField(financialSupport, 'financingRatio', locale)"></ckeditor>
                    </div>
                    <div class="col-md-8" v-else>
                        <label for="text">Finanzierung (Übersetzung {{ locale.toUpperCase() }})</label>
                        <ckeditor id="text" :editor="editor" :config="editorConfig"
                                  v-model="financialSupport.translations[locale].financingRatio" :placeholder="translateField(financialSupport, 'financingRatio', locale)"></ckeditor>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6">
                        <label for="geographicRegions">Fördergebiet</label>
                        <tag-selector id="geographicRegions" :model="financialSupport.geographicRegions"
                                      :options="geographicRegions.filter(geographicRegion => !geographicRegion.context || geographicRegion.context === 'financial-support')" :searchType="'select'"></tag-selector>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-12">
                        <label v-if="locale === 'de'">Kontakte</label>
                        <label v-else>Kontakte (Übersetzung {{ locale.toUpperCase() }})</label>
                        <div class="financial-support-component-form-section-contact" v-for="(contact, index) in (locale === 'de' ? financialSupport.contacts : financialSupport.translations[locale].contacts)">
                            <div class="row">
                                <div class="col-md-8">
                                    <label>Name</label>
                                    <input type="text" class="form-control" v-model="contact.name">
                                </div>
                                <div class="col-md-4">
                                    <label>Typ</label>
                                    <div class="select-wrapper">
                                        <select class="form-control" v-model="contact.type" @change="cleanContactFields(contact)">
                                            <option value="person">Person</option>
                                            <option value="institution">Institution</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                            <div class="row" v-if="contact.type !== 'institution'">
                                <div class="col-md-2">
                                    <label>Anrede</label>
                                    <div class="select-wrapper">
                                        <select class="form-control" v-model="contact.salutation">
                                            <option value=""></option>
                                            <option value="m">Herr</option>
                                            <option value="f">Frau</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-2">
                                    <label>Titel</label>
                                    <input type="text" class="form-control" v-model="contact.title">
                                </div>
                                <div class="col-md-4">
                                    <label>Vorname</label>
                                    <input type="text" class="form-control" v-model="contact.firstName">
                                </div>
                                <div class="col-md-4">
                                    <label>Nachname</label>
                                    <input type="text" class="form-control" v-model="contact.lastName">
                                </div>
                            </div>
                            <div class="row">
                                <div  v-if="contact.type !== 'institution'" class="col-md-6">
                                    <label>Funktion</label>
                                    <input type="text" class="form-control" v-model="contact.role">
                                </div>
                                <div class="col-md-6">
                                    <label>Abteilung / Sektion der Institution</label>
                                    <input type="text" class="form-control" v-model="contact.department">
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-4">
                                    <label>Telefon</label>
                                    <input type="text" class="form-control" v-model="contact.phone">
                                </div>
                                <div class="col-md-4">
                                    <label>E-Mail</label>
                                    <input type="text" class="form-control" v-model="contact.email">
                                </div>
                                <div class="col-md-4">
                                    <label>Website</label>
                                    <input type="text" class="form-control" v-model="contact.website">
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-5">
                                    <label>Strasse</label>
                                    <input type="text" class="form-control" v-model="contact.street">
                                </div>
                                <div class="col-md-3">
                                    <label>PLZ</label>
                                    <input type="text" class="form-control" v-model="contact.zipCode">
                                </div>
                                <div class="col-md-4">
                                    <label>Ort</label>
                                    <input type="text" class="form-control" v-model="contact.city">
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-12">
                                    <label>Adresszusatz</label>
                                    <input type="text" class="form-control" v-model="contact.addressSupplement">
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-12">
                                    <div class="button warning" @click="clickRemoveContact(index)">Kontakt entfernen</div>
                                </div>
                            </div>
                        </div>
                        <div class="financial-support-component-form-section-contact">
                            <div class="button success" @click="clickAddContact()">Kontakt hinzufügen</div>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-8">
                        <label v-if="locale === 'de'">{{ $t('Mehr Informationen') }}</label>
                        <label v-else>{{ $t('Mehr Informationen') }} (Übersetzung {{ locale.toUpperCase() }})</label>
                        <draggable 
                            v-model="currentLinks" 
                            item-key="index" 
                            handle=".drag-handle"
                            class="draggable-links">
                            <template #item="{ element: link, index }">
                                <div class="row draggable-item">
                                    <div class="col-md-1">
                                        <div class="drag-handle">
                                            <span class="material-icons">drag_indicator</span>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <input type="text" class="form-control" v-model="link.label" placeholder="Bezeichnung">
                                    </div>
                                    <div class="col-md-4">
                                        <input type="text" class="form-control" v-model="link.value" placeholder="URL">
                                    </div>
                                    <div class="col-md-3">
                                        <button class="button error" @click="clickRemoveLink(index)">Eintrag entfernen</button>
                                    </div>
                                </div>
                            </template>
                        </draggable>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-8">
                        <button class="button success" @click="clickAddLink()">Eintrag hinzufügen</button>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-8">
                        <label v-if="locale === 'de'">{{ $t('Projektbeispiele') }}</label>
                        <label v-else>{{ $t('Projektbeispiele') }} (Übersetzung {{ locale.toUpperCase() }})</label>
                        <draggable 
                            v-model="currentExamples" 
                            item-key="index" 
                            handle=".drag-handle"
                            class="draggable-examples">
                            <template #item="{ element: example, index }">
                                <div class="row draggable-item">
                                    <div class="col-md-1">
                                        <div class="drag-handle">
                                            <span class="material-icons">drag_indicator</span>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <input type="text" class="form-control" v-model="example.label" placeholder="Bezeichnung">
                                    </div>
                                    <div class="col-md-4">
                                        <input type="text" class="form-control" v-model="example.value" placeholder="URL">
                                    </div>
                                    <div class="col-md-3">
                                        <button class="button error" @click="clickRemoveExample(index)">Eintrag entfernen</button>
                                    </div>
                                </div>
                            </template>
                        </draggable>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-8">
                        <button class="button success" @click="clickAddExample()">Eintrag hinzufügen</button>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-3">
                        <label for="startDate">Laufzeit (Start)</label>
                        <date-picker mode="date" :is24hr="true" v-model="financialSupport.startDate" :locale="'de'">
                            <template v-slot="{ inputValue, inputEvents }">
                                <input type="text" class="form-control"
                                       :value="inputValue"
                                       v-on="inputEvents"
                                       id="startDate">
                            </template>
                        </date-picker>
                    </div>
                    <div class="col-md-3">
                        <label for="endDate">Laufzeit (Ende)</label>
                        <date-picker mode="date" :is24hr="true" v-model="financialSupport.endDate" :locale="'de'">
                            <template v-slot="{ inputValue, inputEvents }">
                                <input type="text" class="form-control"
                                       :value="inputValue"
                                       v-on="inputEvents"
                                       id="endDate">
                            </template>
                        </date-picker>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-12">
                        <label v-if="locale === 'de'">{{ $t('Termine') }}</label>
                        <label v-else>{{ $t('Termine') }} (Übersetzung {{ locale.toUpperCase() }})</label>
                        <div class="financial-support-component-form-section-appointments">
                            <div class="row" v-for="(appointment, index) in (locale === 'de' ? financialSupport.appointments : financialSupport.translations[locale].appointments)">
                                <div class="col-md-3">
                                    <label>{{ $t('Datum') }}</label>
                                    <date-picker mode="date" :is24hr="true" v-model="appointment.date" :locale="'de'">
                                        <template v-slot="{ inputValue, inputEvents }">
                                            <input type="text" class="form-control"
                                                   :value="inputValue"
                                                   v-on="inputEvents"
                                                   :placeholder="$t('Datum')">
                                        </template>
                                    </date-picker>
                                </div>
                                <div class="col-md-6">
                                    <label>{{ $t('Beschreibung') }}</label>
                                    <ckeditor :editor="editor" :config="editorConfig"
                                              v-model="appointment.description" :placeholder="$t('Beschreibung')"></ckeditor>
                                </div>
                                <div class="col-md-3">
                                    <button class="button error" @click="clickRemoveAppointment(index)">{{ $t('Termin entfernen') }}</button>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-12">
                                    <button class="button success" @click="clickAddAppointment()">{{ $t('Termin hinzufügen') }}</button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>


                <div class="row">
                    <div class="col-md-6">
                        <label for="zuteilung">Art der Förderung</label>
                        <div class="select-wrapper">
                            <select id="zuteilung" class="form-control" v-model="financialSupport.assignment">
                                <option value=""></option>
                                <option value="Finanziell">Finanziell</option>
                                <option value="Nicht-Finanziell">Nicht-Finanziell</option>
                                <option value="beides">beides</option>
                            </select>
                        </div>
                    </div>
                </div>

            </div>

        </div>

        <transition name="fade">
            <Modal v-if="modal" :config="modal"></Modal>
        </transition>

    </div>

</template>

<script>
import {mapGetters, mapState} from 'vuex';
import ClassicEditor from '@ckeditor/ckeditor5-build-classic';
import TagSelector from './TagSelector';
import TagSearchSelect from './TagSearchSelect';
import ImageSelector from './ImageSelector';
import FileSelector from './FileSelector';
import { DatePicker } from 'v-calendar';
import Modal from './Modal';
import {translateField} from '../utils/filters';
import draggable from 'vuedraggable';

export default {
    data() {
        return {
            locale: 'de',
            financialSupport: {
                position: 10000,
                isPublic: false,
                name: '',
                description: '',
                additionalInformation: '',
                policies: '',
                application: '',
                applicationTips: '',
                inclusionCriteria: '',
                exclusionCriteria: '',
                financingRatio: '',
                res: '',
                startDate: null,
                endDate: null,
                assignment: '',
                links: [],
                examples: [],
                logo: null,
                fundingProvider: '',
                beneficiaries: [],
                topics: [],
                projectTypes: [],
                instruments: [],
                geographicRegions: [],
                contacts: [],
                appointments: [],
                otherOptionValues: {
                    beneficiary: '',
                    instrument: ''
                },
                translations: {
                    fr: {
                        name: '',
                        description: '',
                        additionalInformation: '',
                        policies: '',
                        application: '',
                        applicationTips: '',
                        inclusionCriteria: '',
                        exclusionCriteria: '',
                        financingRatio: '',
                        res: '',
                        assignment: '',
                        links: [],
                        examples: [],
                        logo: null,
                        contacts: [],
                        appointments: [],
                        otherOptionValues: {
                            beneficiary: '',
                            instrument: ''
                        },
                        fundingProvider: ''
                    },
                    it: {
                        name: '',
                        description: '',
                        additionalInformation: '',
                        policies: '',
                        application: '',
                        applicationTips: '',
                        inclusionCriteria: '',
                        exclusionCriteria: '',
                        financingRatio: '',
                        res: '',
                        assignment: '',
                        links: [],
                        examples: [],
                        logo: null,
                        contacts: [],
                        appointments: [],
                        otherOptionValues: {
                            beneficiary: '',
                            instrument: ''
                        },
                        fundingProvider: ''
                    }
                },
            },
            modal: null,
            publicationStatus: {
                live: null,
                staging: null
            },
            editor: ClassicEditor,
            editorConfig: {
                basicEntities: false,
                toolbar: {
                    items: [
                        'heading',
                        '|',
                        'bold',
                        'italic',
                        'link',
                        '|',
                        'numberedList',
                        'bulletedList',
                        'insertTable',
                        '|',
                        'undo',
                        'redo',
                    ]
                },
                link: {
                    decorators: {
                        openInNewTab: {
                            mode: 'automatic',
                            callback: () => true, // Applies to all links
                            attributes: {
                                target: '_blank',
                                rel: 'noopener noreferrer'
                            }
                        }
                    }
                }
            },
        };
    },
    components: {
        TagSelector,
        TagSearchSelect,
        ImageSelector,
        FileSelector,
        DatePicker,
        Modal,
        draggable,
    },
    computed: {
        ...mapState({
            selectedFinancialSupport: state => state.financialSupports.financialSupport,
            beneficiaries: state => state.beneficiaries.all,
            topics: state => state.topics.all,
            projectTypes: state => state.projectTypes.all,
            instruments: state => state.instruments.all,
            geographicRegions: state => state.geographicRegions.all,
        }),
        ...mapGetters({
            getBeneficiaryById: 'beneficiaries/getById',
            getInstrumentById: 'instruments/getById',
        }),
        hasWeitereBeneficiary() {
            return this.financialSupport.beneficiaries && 
                   this.financialSupport.beneficiaries.some(b => this.getBeneficiaryById(b.id)?.name === 'Weitere');
        },
        hasWeitereInstrument() {
            return this.financialSupport.instruments && 
                   this.financialSupport.instruments.some(i => this.getInstrumentById(i.id)?.name === 'Weitere');
        },
        currentOtherOptionValues: {
            get() {
                if (this.locale === 'de') {
                    return this.financialSupport.otherOptionValues || {};
                }
                return (this.financialSupport.translations[this.locale] || {}).otherOptionValues || {};
            },
            set(value) {
                if (this.locale === 'de') {
                    this.financialSupport.otherOptionValues = value;
                } else {
                    if (!this.financialSupport.translations[this.locale]) {
                        this.financialSupport.translations[this.locale] = {};
                    }
                    this.financialSupport.translations[this.locale].otherOptionValues = value;
                }
            }
        },
        currentLinks: {
            get() {
                if (this.locale === 'de') {
                    return this.financialSupport.links || [];
                }
                return (this.financialSupport.translations[this.locale] || {}).links || [];
            },
            set(value) {
                if (this.locale === 'de') {
                    this.financialSupport.links = value;
                } else {
                    if (!this.financialSupport.translations[this.locale]) {
                        this.financialSupport.translations[this.locale] = {};
                    }
                    this.financialSupport.translations[this.locale].links = value;
                }
            }
        },
        currentExamples: {
            get() {
                if (this.locale === 'de') {
                    return this.financialSupport.examples || [];
                }
                return (this.financialSupport.translations[this.locale] || {}).examples || [];
            },
            set(value) {
                if (this.locale === 'de') {
                    this.financialSupport.examples = value;
                } else {
                    if (!this.financialSupport.translations[this.locale]) {
                        this.financialSupport.translations[this.locale] = {};
                    }
                    this.financialSupport.translations[this.locale].examples = value;
                }
            }
        },
    },
    methods: {
        clickDelete () {
            this.modal = {
                title: 'Eintrag löschen',
                description: 'Sind Sie sicher dass Sie diesen Eintrag unwiderruflich löschen möchten?',
                actions: [
                    {
                        label: 'Endgültig löschen',
                        class: 'error',
                        onClick: () => {
                            this.$store.dispatch('financialSupports/delete', this.financialSupport.id).then(() => {
                                this.$router.push('/financial-supports');
                            });
                        }
                    },
                    {
                        label: 'Abbrechen',
                        class: 'warning',
                        onClick: () => {
                            this.modal = null;
                        }
                    }
                ],
            };
        },
        clickCancel () {
            this.$router.push('/financial-supports');
        },
        async loadPublicationStatus() {
            if (!this.financialSupport.id) return;
            
            try {
                const response = await fetch('/api/v1/financial-supports/publication-status');
                const result = await response.json();
                
                // Reset status
                this.publicationStatus.live = null;
                this.publicationStatus.staging = null;
                
                if (Array.isArray(result)) {
                    // Find this financial support in the data
                    const fsData = result.find(fs => fs.id === this.financialSupport.id);
                    
                    if (fsData) {
                        this.publicationStatus.live = fsData.production 
                            ? new Date(fsData.production.publishedAt).toLocaleString('de-DE')
                            : null;
                        this.publicationStatus.staging = fsData.staging 
                            ? new Date(fsData.staging.publishedAt).toLocaleString('de-DE')
                            : null;
                    }
                }
                
            } catch (error) {
                console.error('Error loading publication status:', error);
            }
        },
        clickSave() {
            if(!this.financialSupport.startDate) {
                this.financialSupport.startDate = null;
            }

            if(!this.financialSupport.endDate) {
                this.financialSupport.endDate = null;
            }

            // Clean person-specific fields for institution contacts before saving
            ['de', 'fr', 'it'].forEach(locale => {
                const contacts = locale === 'de' ? this.financialSupport.contacts : (this.financialSupport.translations[locale] ? this.financialSupport.translations[locale].contacts : []);
                if (contacts) {
                    contacts.forEach(contact => {
                        // Set default type if not set
                        if (!contact.type) {
                            contact.type = 'person';
                        }
                        // Clean person fields for institutions
                        if (contact.type === 'institution') {
                            contact.salutation = '';
                            contact.title = '';
                            contact.firstName = '';
                            contact.lastName = '';
                            contact.role = '';
                        }
                    });
                }
            });

            // Initialize otherOptionValues if it doesn't exist
            if (!this.financialSupport.otherOptionValues) {
                this.financialSupport.otherOptionValues = {
                    beneficiary: '',
                    instrument: ''
                };
            }

            // Get the current values based on locale
            const currentValues = this.currentOtherOptionValues;
            
            // Update otherOptionValues based on locale
            if (this.locale === 'de') {
                this.financialSupport.otherOptionValues = {
                    beneficiary: currentValues.beneficiary || '',
                    instrument: currentValues.instrument || ''
                };
            } else {
                if (!this.financialSupport.translations[this.locale]) {
                    this.financialSupport.translations[this.locale] = {};
                }
                this.financialSupport.translations[this.locale].otherOptionValues = {
                    beneficiary: currentValues.beneficiary || '',
                    instrument: currentValues.instrument || ''
                };
            }

            const savePromise = this.financialSupport.id ? 
                this.$store.dispatch('financialSupports/update', this.financialSupport) :
                this.$store.dispatch('financialSupports/create', this.financialSupport);

            // Create an array of promises to reload all necessary data
            const reloadPromises = [
                savePromise,
                this.$store.dispatch('beneficiaries/loadAll'),
                this.$store.dispatch('instruments/loadAll')
            ];

            Promise.all(reloadPromises).then(() => {
                this.$router.push('/financial-supports');
            });
        },
        reload() {
            if(this.$route.params.id) {
                this.$store.commit('financialSupports/set', {});
                this.$store.dispatch('financialSupports/load', this.$route.params.id).then(() => {
                    // Create a deep copy of the selected financial support
                    this.financialSupport = JSON.parse(JSON.stringify(this.selectedFinancialSupport));
                    
                    // Ensure otherOptionValues is properly initialized
                    if (!this.financialSupport.otherOptionValues) {
                        this.financialSupport.otherOptionValues = {
                            beneficiary: '',
                            instrument: ''
                        };
                    }
                    
                    // Ensure translations have otherOptionValues initialized
                    ['fr', 'it'].forEach(locale => {
                        if (!this.financialSupport.translations[locale]) {
                            this.financialSupport.translations[locale] = {};
                        }
                        if (!this.financialSupport.translations[locale].otherOptionValues) {
                            this.financialSupport.translations[locale].otherOptionValues = {
                                beneficiary: '',
                                instrument: ''
                            };
                        }
                    });
                    
                    // Ensure contacts have new fields initialized
                    ['de', 'fr', 'it'].forEach(locale => {
                        const contacts = locale === 'de' ? this.financialSupport.contacts : (this.financialSupport.translations[locale] ? this.financialSupport.translations[locale].contacts : []);
                        if (contacts) {
                            contacts.forEach(contact => {
                                // Initialize new fields if they don't exist
                                if (contact.type === undefined) contact.type = 'person';
                                if (contact.department === undefined) contact.department = '';
                                if (contact.addressSupplement === undefined) contact.addressSupplement = '';
                            });
                        }
                    });
                });
            }
        },
        clickAddLink() {
            (this.locale === 'de' ? this.financialSupport.links : this.financialSupport.translations[this.locale].links).push({
                value: '',
                label: '',
            });
        },
        clickRemoveLink(index) {
            let link = (this.locale === 'de' ? this.financialSupport.links : this.financialSupport.translations[this.locale].links).splice(index, 1)[0];
        },
        clickAddExample() {
            (this.locale === 'de' ? this.financialSupport.examples : this.financialSupport.translations[this.locale].examples).push({
                value: '',
                label: '',
            });
        },
        clickRemoveExample(index) {
            let example = (this.locale === 'de' ? this.financialSupport.examples : this.financialSupport.translations[this.locale].examples).splice(index, 1)[0];
        },
        clickAddContact() {
            (this.locale === 'de' ? this.financialSupport.contacts : this.financialSupport.translations[this.locale].contacts).push({
                type: 'person',
                name: '',
                salutation: '',
                title: '',
                firstName: '',
                lastName: '',
                role: '',
                department: '',
                phone: '',
                email: '',
                website: '',
                street: '',
                zipCode: '',
                city: '',
                addressSupplement: ''
            });
        },
        clickRemoveContact(index) {
            let contact = (this.locale === 'de' ? this.financialSupport.contacts : this.financialSupport.translations[this.locale].contacts).splice(index, 1)[0];
        },
        cleanContactFields(contact) {
            // Clean person-specific fields when switching to institution
            if (contact.type === 'institution') {
                contact.salutation = '';
                contact.title = '';
                contact.firstName = '';
                contact.lastName = '';
                contact.role = '';
                contact.department = '';
            }
        },
        clickAddAppointment() {
            if (this.locale === 'de') {
                if (!this.financialSupport.appointments) {
                    this.financialSupport.appointments = [];
                }
                this.financialSupport.appointments.push({
                    date: null,
                    description: '',
                });
            } else {
                if (!this.financialSupport.translations[this.locale].appointments) {
                    this.financialSupport.translations[this.locale].appointments = [];
                }
                this.financialSupport.translations[this.locale].appointments.push({
                    date: null,
                    description: '',
                });
            }
        },
        clickRemoveAppointment(index) {
            if (this.locale === 'de') {
                this.financialSupport.appointments.splice(index, 1);
            } else {
                this.financialSupport.translations[this.locale].appointments.splice(index, 1);
            }
        },
        translateField,
    },
    created () {
        this.reload();
    },
    watch: {
        'financialSupport.isPublic': function(newVal, oldVal) {
            if (newVal !== oldVal) {
                this.loadPublicationStatus();
            }
        },
        'financialSupport.id': function(newVal, oldVal) {
            if (newVal !== oldVal && newVal) {
                this.loadPublicationStatus();
            }
        }
    }
}
</script>

<style scoped>
.draggable-links, .draggable-examples {
    margin-bottom: 10px;
}

.draggable-item {
    margin-bottom: 10px;
    padding: 10px;
    border-radius: 4px;
}


.drag-handle {
    cursor: move;
    display: flex;
    align-items: center;
    justify-content: center;
    height: 34px;
    color: #666;
    border: 1px solid #ccc;
    border-radius: 4px;
    background-color: #fff;
    transition: all 0.2s;
}

.drag-handle:hover {
    background-color: #f0f0f0;
    color: #333;
}

.drag-handle .material-icons {
    font-size: 18px;
}

.publication-status-section {
    padding: 15px;
    margin-bottom: 20px;
}

.publication-status-container {
    display: flex;
    gap: 30px;
    align-items: center;
}

.publication-status-item {
    display: flex;
    align-items: center;
    gap: 8px;
}

.publication-status-label {
    font-weight: 600;
    color: #495057;
    font-size: 14px;
}

.publication-status-value {
    padding: 4px 8px;
    border-radius: 4px;
    font-size: 12px;
    font-weight: 500;
}

.publication-status-value.published {
    background-color: #d4edda;
    color: #155724;
}

.publication-status-value.not-published {
    background-color: #f8d7da;
    color: #721c24;
}
</style>