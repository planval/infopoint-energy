<template>

    <div class="financial-supports-component">

        <div class="financial-supports-component-title">

            <h2>Förderhilfen</h2>

            <transition name="fade" mode="out-in">
                <div class="loading-indicator" v-if="isLoading('financialSupports')"></div>
            </transition>

            <div class="financial-supports-component-title-actions">
                <router-link :to="'/financial-supports/add'" class="button primary">Neuen Eintrag erstellen</router-link>
                <button @click="exportAll" class="button primary">Export</button>
                <button @click="showPublishDialog" class="button primary">Förderhilfen publizieren</button>
            </div>

        </div>

        <div class="financial-supports-component-filter">
            <div class="row">
                <div class="col-md-8">
                    <div class="form-group">
                        <label for="term">Suchbegriff</label>
                        <input id="term" type="text" class="form-control" v-model="term" @change="changeForm()">
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="form-group">
                        <label for="status">Status</label>
                        <div class="select-wrapper">
                            <select id="status" class="form-control" @change="addFilter({type: 'status', value: $event.target.value}, true); $event.target.value = null;">
                                <option></option>
                                <option :value="'public'">Öffentlich</option>
                                <option :value="'draft'">Entwurf</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="form-group">
                        <label for="startDate">Laufzeit (Start)</label>
                        <input 
                            style="padding: 0.6em;"
                            type="date" 
                            id="startDate" 
                            class="form-control" 
                            @change="addFilter({type: 'startDate', value: $event.target.value}, true)"
                        >
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="form-group">
                        <label for="endDate">Laufzeit (Ende)</label>
                        <input 
                            style="padding: 0.6em;"
                            type="date" 
                            id="endDate" 
                            class="form-control" 
                            @change="addFilter({type: 'endDate', value: $event.target.value}, true)"
                        >
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="form-group">
                        <label for="appointmentStartDate">Termine (Start)</label>
                        <input 
                            style="padding: 0.6em;"
                            type="date" 
                            id="appointmentStartDate" 
                            class="form-control" 
                            @change="addFilter({type: 'appointmentStartDate', value: $event.target.value}, true)"
                        >
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="form-group">
                        <label for="appointmentEndDate">Termine (Ende)</label>
                        <input 
                            style="padding: 0.6em;"
                            type="date" 
                            id="appointmentEndDate" 
                            class="form-control" 
                            @change="addFilter({type: 'appointmentEndDate', value: $event.target.value}, true)"
                        >
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="form-group">
                        <label for="dateStatus">Laufzeit (Zeitraum)</label>
                        <div class="select-wrapper">
                            <select id="dateStatus" class="form-control" @change="addFilter({type: 'dateStatus', value: $event.target.value}, true); $event.target.value = null;">
                                <option></option>
                                <option value="active">{{ 'Aktive' }}</option>
                                <option value="endedToday">{{ 'Abgelaufen: Heute' }}</option>
                                <option value="endedYesterday">{{ 'Abgelaufen: Gestern' }}</option>
                                <option value="endedThisWeek">{{ 'Abgelaufen: diese Woche' }}</option>
                                <option value="endedThisMonth">{{ 'Abgelaufen: diesen Monat' }}</option>
                                <option value="endedThisYear">{{ 'Abgelaufen: dieses Jahr' }}</option>
                                <option value="startedYesterday">{{ 'Gestartet: Gestern' }}</option>
                                <option value="startedToday">{{ 'Gestartet: Heute' }}</option>
                                <option value="startedThisWeek">{{ 'Gestartet: diese Woche' }}</option>
                                <option value="startedThisMonth">{{ 'Gestartet: diesen Monat' }}</option>
                                <option value="startedThisYear">{{ 'Gestartet: dieses Jahr' }}</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="form-group">
                        <label for="appointment">Termine (Zeitraum)</label>
                        <div class="select-wrapper">
                            <select id="appointment" class="form-control" @change="addFilter({type: 'appointment', value: $event.target.value}, true); $event.target.value = null;">
                                <option></option>
                                <option value="yesterday">{{ 'Gestern' }}</option>
                                <option value="today">{{ 'Heute' }}</option>
                                <option value="tomorrow">{{ 'Morgen' }}</option>
                                <option value="thisWeek">{{ 'Diese Woche' }}</option>
                                <option value="thisMonth">{{ 'Dieser Monat' }}</option>
                                <option value="nextMonth">{{ 'Nächster Monat' }}</option>
                                <option value="expiredAll">{{ 'Abgelaufen - Alle' }}</option>
                                <option value="expiredThisMonth">{{ 'Abgelaufen - diesen Monat' }}</option>
                                <option value="expiredThisWeek">{{ 'Abgelaufen - diese Woche' }}</option>
                                <option value="expiredThisYear">{{ 'Abgelaufen - dieses Jahr' }}</option>
                            </select>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-sm-3">
                    <div class="form-group">
                        <label for="fundingProvider">Förderstelle</label>
                        <input id="fundingProvider" type="text" class="form-control" @change="addFilter({type: 'fundingProvider', value: $event.target.value}); $event.target.value = '';">
                    </div>
                </div>
                <div class="col-sm-3">
                    <div class="form-group">
                        <label for="state">Kanton</label>
                        <div class="select-wrapper">
                            <select id="state" class="form-control" @change="addFilter({type: 'state', value: $event.target.value}); $event.target.value = null;">
                                <option></option>
                                <option v-for="state in states.filter(state => !state.context || state.context === 'financial-support')" :value="state.name">{{state.name}}</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="col-sm-3">
                    <div class="form-group">
                        <label for="topic">Thema</label>
                        <div class="select-wrapper">
                            <select id="topic" class="form-control" @change="addFilter({type: 'topic', value: $event.target.value}); $event.target.value = null;">
                                <option></option>
                                <option v-for="topic in topics.filter(topic => !topic.context || topic.context === 'financial-support')" :value="topic.name">{{topic.name}}</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="col-sm-3">
                    <div class="form-group">
                        <label for="instrument">Unterstützungsart</label>
                        <div class="select-wrapper">
                            <select id="instrument" class="form-control" @change="addFilter({type: 'instrument', value: $event.target.value}); $event.target.value = null;">
                                <option></option>
                                <option v-for="instrument in instruments.filter(instrument => !instrument.context || instrument.context === 'financial-support')" :value="instrument.name">{{instrument.name}}</option>
                            </select>
                        </div>
                    </div>
                </div>
            </div>

            <div class="financial-supports-component-filter-tags">
                <div class="tag" v-for="filter of filters" @click="removeFilter({type: filter.type, value: filter.value})">
                    <strong v-if="filter.type === 'status'">Status:</strong>
                    <strong v-if="filter.type === 'fundingProvider'">Förderstelle:</strong>
                    <strong v-if="filter.type === 'state'">Kanton:</strong>
                    <strong v-if="filter.type === 'topic'">Thema:</strong>
                    <strong v-if="filter.type === 'instrument'">Unterstützungsart:</strong>
                    <strong v-if="filter.type === 'appointment'">Termine:</strong>
                    <strong v-if="filter.type === 'startDate'">Laufzeit (Start):</strong>
                    <strong v-if="filter.type === 'endDate'">Laufzeit (Ende):</strong>
                    <strong v-if="filter.type === 'dateStatus'">Laufzeit Status:</strong>
                    <strong v-if="filter.type === 'appointmentStartDate'">Termine (Start):</strong>
                    <strong v-if="filter.type === 'appointmentEndDate'">Termine (Ende):</strong>
                    <template v-if="filter.type === 'status'">
                        &nbsp;{{ filter.value === 'public' ? 'Öffentlich' : 'Entwurf' }}
                    </template>
                    <template v-else-if="filter.type === 'appointment'">
                        &nbsp;{{ getAppointmentLabel(filter.value) }}
                    </template>
                    <template v-else-if="filter.type === 'startDate' || filter.type === 'endDate' || filter.type === 'appointmentStartDate' || filter.type === 'appointmentEndDate'">
                        &nbsp;{{ new Date(filter.value).toLocaleDateString() }}
                    </template>
                    <template v-else-if="filter.type === 'dateStatus'">
                        &nbsp;{{ getDateStatusLabel(filter.value) }}
                    </template>
                    <template v-else>
                        &nbsp;{{ filter.value }}
                    </template>
                </div>
            </div>
        </div>

        <div class="financial-supports-component-content">

            <table class="table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Bezeichnung</th>
                        <th>Förderstelle</th>
                        <th>Laufzeit</th>
                        <th>PDF</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody v-if="isLoading('financialSupports')">
                    <tr>
                        <td colspan="11"><em>Einträge werden geladen...</em></td>
                    </tr>
                </tbody>
                <tbody v-else-if="!localFinancialSupports.length">
                    <tr>
                        <td colspan="11"><em>Keine Einträge gefunden</em></td>
                    </tr>
                </tbody>
                <draggable v-else :list="filteredFinancialSupports" :tag="'tbody'" item-key="id" @change="changeSort">
                    <template #item="{element}">
                        <tr class="clickable"
                            @click="clickFinancialSupport(element)"
                            :class="{'warning': !element.isPublic}">
                            <td>{{ element.id }}</td>
                            <td>{{ translateField(element, 'name', 'de') }}</td>
                            <td>{{ element.fundingProvider }}</td>
                            <td>{{ $helpers.formatDate(element.startDate) }} - {{ $helpers.formatDate(element.endDate) }}</td>
                            <td @click.stop>
                                <a :href="`/api/v1/financial-supports/export/${element.id}-de.pdf`" 
                                   target="_blank" 
                                   class="button small">
                                    PDF
                                </a>
                            </td>
                            <td @click.stop>
                                <div class="publication-status-dots">
                                    <div class="status-dot-container">
                                        <div class="status-dot" 
                                             :class="getPublicationStatusClass(element.id, 'staging')"
                                             :title="getPublicationStatusTooltip(element.id, 'staging')">
                                        </div>
                                        <span class="status-label">Test</span>
                                    </div>
                                    <div class="status-dot-container">
                                        <div class="status-dot" 
                                             :class="getPublicationStatusClass(element.id, 'production')"
                                             :title="getPublicationStatusTooltip(element.id, 'production')">
                                        </div>
                                        <span class="status-label">Live</span>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    </template>
                </draggable>
            </table>

        </div>

        <transition name="fade" mode="in-out">

            <div class="context-bar" v-if="isSortChanged">
                <div class="context-bar-content">
                    <p v-if="!isLoading('financialSupports/*')">Sortierung geändert. Möchten Sie die Änderungen speichern?</p>
                    <p v-else>{{ sortChangeProgress }} von {{ localFinancialSupports.length }} Positionen gespeichert...</p>
                </div>
                <template v-if="!isLoading('financialSupports/*')">
                    <a class="button warning" @click="clickRestoreSort()">Zurücksetzen</a>
                    <a class="button success" @click="clickSaveSort()">Speichern</a>
                </template>
            </div>

        </transition>

        <!-- Modern modal for publish environment selection -->
        <div class="modal-modern" v-if="showPublishModal">
            <div class="modal-backdrop" @click="showPublishModal = false"></div>
            <div class="modal-content-modern">
                <div class="modal-header-modern">
                    <h3>Förderhilfen publizieren</h3>
                    <button class="close-button-modern" @click="showPublishModal = false">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <line x1="18" y1="6" x2="6" y2="18"></line>
                            <line x1="6" y1="6" x2="18" y2="18"></line>
                        </svg>
                    </button>
                </div>
                
                <div class="modal-body-modern">
                    <!-- Show environment selection if not confirming -->
                    <div v-if="!confirmingPublish && !isPublishing">
                        <p class="modal-description">Wählen Sie die Umgebung für die Publikation:</p>
                        <div class="environment-cards">
                            <div class="environment-card" @click="confirmPublish('production')" :class="{'disabled': isPublishing}">
                                <h4>Live-Umgebung</h4>
                                <p>Publikation für Endnutzer</p>
                            </div>
                            <div class="environment-card" @click="confirmPublish('staging')" :class="{'disabled': isPublishing}">
                                <h4>Test-Umgebung</h4>
                                <p>Publikation für Tests</p>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Show confirmation if user selected an environment -->
                    <div v-if="confirmingPublish && !isPublishing">
                        <div class="confirmation-content">
                            <h4>Publikation bestätigen</h4>
                            <p class="confirmation-message">
                                Wollen Sie wirklich zur <strong>{{ confirmingEnvironment === 'production' ? 'Live-Umgebung' : 'Test-Umgebung' }}</strong> publizieren?
                            </p>
                            <div class="confirmation-buttons-modern">
                                <button @click="cancelConfirmation()" class="button secondary" :disabled="isPublishing">
                                    Abbrechen
                                </button>
                                <button @click="publishToEnvironment(confirmingEnvironment)" class="button primary" :disabled="isPublishing">
                                    Bestätigen
                                </button>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Modern loading state -->
                    <div v-if="isPublishing" class="publishing-status-modern">
                        <div class="loading-container">
                            <div class="modern-spinner"></div>
                            <div class="loading-content">
                                <h4>Publikation wird durchgeführt</h4>
                                <p>Bitte warten Sie, während die Daten übertragen werden...</p>
                                <div class="loading-steps">
                                    <div class="step active">
                                        <div class="step-indicator"></div>
                                        <span>Dateien vorbereiten</span>
                                    </div>
                                    <div class="step">
                                        <div class="step-indicator"></div>
                                        <span>Upload durchführen</span>
                                    </div>
                                    <div class="step">
                                        <div class="step-indicator"></div>
                                        <span>Publikation abschließen</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Modern status feedback -->
                    <div v-if="publishStatus" class="publish-status-modern" :class="{'success': publishStatus.success, 'error': !publishStatus.success}">
                        <div class="status-icon">
                            <svg v-if="publishStatus.success" width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
                                <polyline points="22,4 12,14.01 9,11.01"></polyline>
                            </svg>
                            <svg v-else width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <circle cx="12" cy="12" r="10"></circle>
                                <line x1="15" y1="9" x2="9" y2="15"></line>
                                <line x1="9" y1="9" x2="15" y2="15"></line>
                            </svg>
                        </div>
                        <div class="status-content">
                            <h4 v-if="publishStatus.success">Publikation erfolgreich</h4>
                            <h4 v-else>Publikation fehlgeschlagen</h4>
                            <p>{{ publishStatus.message }}</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

    </div>

</template>

<script>
    import { mapState, mapGetters } from 'vuex';
    import { translateField } from '../utils/filters';
    import draggable from 'vuedraggable';

    export default {
        data () {
            return {
                term: '',
                filters: [],
                isSortChanged: false,
                sortChangeProgress: 0,
                localFinancialSupports: [],
                limit: 100,
                offset: 0,
                isLoadedFully: false,
                showPublishModal: false,
                isPublishing: false,
                publishStatus: null,
                confirmingPublish: false,
                confirmingEnvironment: null,
                publicationStatusMap: {}, // Map to store publication status for each financial support
            };
        },
        components: {
            draggable,
        },
        computed: {
            ...mapState({
                authorities: state => state.authorities.all,
                states: state => state.states.all,
                topics: state => state.topics.all,
                instruments: state => state.instruments.all,
                financialSupports: state => state.financialSupports.filtered,
            }),
            ...mapGetters({
                isLoading: 'loaders/isLoading',
                getAuthorityById: 'authorities/getById',
                getBeneficiaryById: 'beneficiaries/getById',
                getTopicById: 'topics/getById',
                getProjectTypeById: 'projectTypes/getById',
                getInstrumentById: 'instruments/getById',
                getGeographicRegionById: 'geographicRegions/getById',
            }),
            filteredFinancialSupports() {
                if (!this.filters.find(f => f.type === 'appointment' || f.type === 'startDate' || f.type === 'endDate' || f.type === 'dateStatus' || f.type === 'appointmentStartDate' || f.type === 'appointmentEndDate')) {
                    return this.localFinancialSupports;
                }

                const today = new Date();
                today.setHours(0, 0, 0, 0);

                const tomorrow = new Date(today);
                tomorrow.setDate(tomorrow.getDate() + 1);

                const yesterday = new Date(today);
                yesterday.setDate(yesterday.getDate() - 1);

                const startOfWeek = new Date(today);
                startOfWeek.setDate(today.getDate() - today.getDay());

                const endOfWeek = new Date(startOfWeek);
                endOfWeek.setDate(endOfWeek.getDate() + 6);

                const startOfMonth = new Date(today.getFullYear(), today.getMonth(), 1);
                const endOfMonth = new Date(today.getFullYear(), today.getMonth() + 1, 0);

                const startOfNextMonth = new Date(today.getFullYear(), today.getMonth() + 1, 1);
                const endOfNextMonth = new Date(today.getFullYear(), today.getMonth() + 2, 0);

                const startOfYear = new Date(today.getFullYear(), 0, 1);
                const endOfYear = new Date(today.getFullYear(), 11, 31);

                const startOfNextYear = new Date(today.getFullYear() + 1, 0, 1);
                const endOfNextYear = new Date(today.getFullYear() + 1, 11, 31);

                // Calculate quarter dates
                const currentQuarter = Math.floor(today.getMonth() / 3);
                const startOfQuarter = new Date(today.getFullYear(), currentQuarter * 3, 1);
                const endOfQuarter = new Date(today.getFullYear(), (currentQuarter + 1) * 3, 0);

                const startOfNextQuarter = new Date(today.getFullYear(), (currentQuarter + 1) * 3, 1);
                const endOfNextQuarter = new Date(today.getFullYear(), (currentQuarter + 2) * 3, 0);

                return this.localFinancialSupports.filter(support => {
                    let matchesFilters = true;

                    // Handle appointment filter
                    const appointmentFilter = this.filters.find(f => f.type === 'appointment');
                    if (appointmentFilter) {
                        const appointments = [
                            ...(support.appointments || []),
                            ...Object.values(support.translations || {}).flatMap(trans => trans.appointments || [])
                        ];

                        matchesFilters = appointments.some(appointment => {
                            if (!appointment || !appointment.date) return false;
                            
                            const appointmentDate = new Date(appointment.date);
                            appointmentDate.setHours(0, 0, 0, 0);

                            switch (appointmentFilter.value) {
                                case 'yesterday':
                                    return appointmentDate.getTime() === yesterday.getTime();
                                case 'today':
                                    return appointmentDate.getTime() === today.getTime();
                                case 'tomorrow':
                                    return appointmentDate.getTime() === tomorrow.getTime();
                                case 'thisWeek':
                                    return appointmentDate >= startOfWeek && appointmentDate <= endOfWeek;
                                case 'thisMonth':
                                    return appointmentDate >= startOfMonth && appointmentDate <= endOfMonth;
                                case 'nextMonth':
                                    return appointmentDate >= startOfNextMonth && appointmentDate <= endOfNextMonth;
                                case 'expiredAll':
                                    return appointmentDate < today;
                                case 'expiredThisMonth':
                                    return appointmentDate < today && appointmentDate >= startOfMonth;
                                case 'expiredThisWeek':
                                    return appointmentDate < today && appointmentDate >= startOfWeek;
                                case 'expiredThisYear':
                                    return appointmentDate < today && appointmentDate >= startOfYear;
                                default:
                                    return true;
                            }
                        });
                    }

                    // Handle startDate filter
                    const startDateFilter = this.filters.find(f => f.type === 'startDate');
                    if (startDateFilter && matchesFilters) {
                        const startDate = support.startDate ? new Date(support.startDate) : null;
                        if (!startDate) return false;

                        startDate.setHours(0, 0, 0, 0);
                        
                        const selectedDate = new Date(startDateFilter.value);
                        selectedDate.setHours(0, 0, 0, 0);

                        matchesFilters = startDate >= selectedDate;
                    }

                    // Handle endDate filter
                    const endDateFilter = this.filters.find(f => f.type === 'endDate');
                    if (endDateFilter && matchesFilters) {
                        const endDate = support.endDate ? new Date(support.endDate) : null;
                        if (!endDate) return false;

                        endDate.setHours(0, 0, 0, 0);
                        
                        const selectedDate = new Date(endDateFilter.value);
                        selectedDate.setHours(0, 0, 0, 0);

                        matchesFilters = endDate <= selectedDate;
                    }

                    // Handle dateStatus filter
                    const dateStatusFilter = this.filters.find(f => f.type === 'dateStatus');
                    if (dateStatusFilter && matchesFilters) {
                        const startDate = support.startDate ? new Date(support.startDate) : null;
                        const endDate = support.endDate ? new Date(support.endDate) : null;
                        if (!startDate || !endDate) return false;

                        startDate.setHours(0, 0, 0, 0);
                        endDate.setHours(0, 0, 0, 0);

                        switch (dateStatusFilter.value) {
                            case 'active':
                                matchesFilters = startDate <= today && endDate >= today;
                                break;
                            case 'endedToday':
                                matchesFilters = endDate.getTime() === today.getTime();
                                break;
                            case 'endedYesterday':
                                matchesFilters = endDate.getTime() === yesterday.getTime();
                                break;
                            case 'endedThisWeek':
                                matchesFilters = endDate >= startOfWeek && endDate <= today;
                                break;
                            case 'endedThisMonth':
                                matchesFilters = endDate >= startOfMonth && endDate <= today;
                                break;
                            case 'endedThisYear':
                                matchesFilters = endDate >= startOfYear && endDate <= today;
                                break;
                            case 'startedYesterday':
                                matchesFilters = startDate.getTime() === yesterday.getTime();
                                break;
                            case 'startedToday':
                                matchesFilters = startDate.getTime() === today.getTime();
                                break;
                            case 'startedThisWeek':
                                matchesFilters = startDate >= startOfWeek && startDate <= today;
                                break;
                            case 'startedThisMonth':
                                matchesFilters = startDate >= startOfMonth && startDate <= today;
                                break;
                            case 'startedThisYear':
                                matchesFilters = startDate >= startOfYear && startDate <= today;
                                break;
                        }
                    }

                    // Handle appointmentStartDate filter
                    const appointmentStartDateFilter = this.filters.find(f => f.type === 'appointmentStartDate');
                    if (appointmentStartDateFilter && matchesFilters) {
                        const appointments = [
                            ...(support.appointments || []),
                            ...Object.values(support.translations || {}).flatMap(trans => trans.appointments || [])
                        ];

                        matchesFilters = appointments.some(appointment => {
                            if (!appointment || !appointment.date) return false;
                            
                            const appointmentDate = new Date(appointment.date);
                            appointmentDate.setHours(0, 0, 0, 0);
                            
                            const selectedDate = new Date(appointmentStartDateFilter.value);
                            selectedDate.setHours(0, 0, 0, 0);

                            return appointmentDate >= selectedDate;
                        });
                    }

                    // Handle appointmentEndDate filter
                    const appointmentEndDateFilter = this.filters.find(f => f.type === 'appointmentEndDate');
                    if (appointmentEndDateFilter && matchesFilters) {
                        const appointments = [
                            ...(support.appointments || []),
                            ...Object.values(support.translations || {}).flatMap(trans => trans.appointments || [])
                        ];

                        matchesFilters = appointments.some(appointment => {
                            if (!appointment || !appointment.date) return false;
                            
                            const appointmentDate = new Date(appointment.date);
                            appointmentDate.setHours(0, 0, 0, 0);
                            
                            const selectedDate = new Date(appointmentEndDateFilter.value);
                            selectedDate.setHours(0, 0, 0, 0);

                            return appointmentDate <= selectedDate;
                        });
                    }

                    return matchesFilters;
                });
            }
        },
        methods: {
            translateField,
            changeForm () {
                this.saveFilter();
                this.reloadFinancialSupports();
            },
            getFilterParams () {
                let params = {};
                params.term = this.term;

                this.filters.forEach((filter) => {
                    if(!params[filter.type]) {
                        params[filter.type] = [];
                    }
                    params[filter.type].push(filter.value);
                });

                params.limit = this.limit;
                params.offset = this.offset;
                params.orderBy = ['position'];
                params.orderDirection = ['ASC'];

                return params;
            },
            reloadFinancialSupports () {
                this.isLoadedFully = false;
                this.offset = 0;
                // Load both financial supports and publication status in parallel
                return Promise.all([
                    this.$store.dispatch('financialSupports/loadFiltered', this.getFilterParams()),
                    this.loadPublicationStatus()
                ]).then(() => {
                    this.localFinancialSupports = [...this.financialSupports];
                });
            },
            clickLoadMore () {
                this.offset += this.limit;
                this.$store.dispatch('financialSupports/loadFiltered', this.getFilterParams()).then(() => {
                    if(!this.financialSupports.length) {
                        this.isLoadedFully = true;
                        return;
                    }
                    this.localFinancialSupports = [
                        ...this.localFinancialSupports,
                        ...this.financialSupports,
                    ];
                });
            },
            clickFinancialSupport (financialSupport) {
                this.$router.push({
                    path: '/financial-supports/'+financialSupport.id+'/edit'
                });
            },
            formatOneToMany (items, getter) {
                let result = [];
                items.forEach((item) => {
                    result.push(getter(item.id)?.name);
                });

                return result.join(', ');
            },
            addFilter (filter, clearExisting = false) {
                if(!filter.value) {
                    return;
                }
                if(clearExisting) {
                    // Remove any existing filters of the same type
                    this.filters = this.filters.filter(f => f.type !== filter.type);
                }
                if(this.filters.filter(f => f.type === filter.type).find(f => f.value === filter.value)) {
                    return;
                }
                this.filters.push(filter);
                this.changeForm();
            },
            removeFilter (filter) {
                let f = this.filters.filter(f => f.type === filter.type).find(f => f.value === filter.value);
                if(f) {
                    this.filters.splice(this.filters.indexOf(f), 1);
                }
                this.changeForm();
            },
            saveFilter () {
                window.sessionStorage.setItem('regiosuisse.financial-supports.filters', JSON.stringify(this.filters));
                window.sessionStorage.setItem('regiosuisse.financial-supports.term', this.term);
            },
            loadFilter () {
                this.filters = JSON.parse(window.sessionStorage.getItem('regiosuisse.financial-supports.filters') || '[]');
                this.term = window.sessionStorage.getItem('regiosuisse.financial-supports.term') || '';
            },
            async clickSaveSort() {
                this.sortChangeProgress = 0;
                for(let key in this.localFinancialSupports) {
                    await this.$store.dispatch('financialSupports/update', {
                        ...this.localFinancialSupports[key],
                        position: key,
                    });
                    this.sortChangeProgress++;
                }
                this.isSortChanged = false;
                this.reloadFinancialSupports();
            },
            clickRestoreSort() {
                this.isSortChanged = false;
                this.reloadFinancialSupports();
            },
            changeSort() {
                this.isSortChanged = true;
            },
            getAppointmentLabel(value) {
                switch(value) {
                    case 'yesterday':
                        return 'Gestern';
                    case 'today':
                        return 'Heute';
                    case 'tomorrow':
                        return 'Morgen';
                    case 'thisWeek':
                        return 'Diese Woche';
                    case 'thisMonth':
                        return 'Dieser Monat';
                    case 'nextMonth':
                        return 'Nächster Monat';
                    case 'expiredAll':
                        return 'Abgelaufen - Alle';
                    case 'expiredThisMonth':
                        return 'Abgelaufen - diesen Monat';
                    case 'expiredThisWeek':
                        return 'Abgelaufen - diese Woche';
                    case 'expiredThisYear':
                        return 'Abgelaufen - dieses Jahr';
                    default:
                        return 'Alle Termine';
                }
            },
            getDateStatusLabel(value) {
                switch(value) {
                    case 'active':
                        return 'Aktive';
                    case 'endedToday':
                        return 'Abgelaufen: Heute';
                    case 'endedYesterday':
                        return 'Abgelaufen: Gestern';
                    case 'endedThisWeek':
                        return 'Abgelaufen: diese Woche';
                    case 'endedThisMonth':
                        return 'Abgelaufen: diesen Monat';
                    case 'endedThisYear':
                        return 'Abgelaufen: dieses Jahr';
                    case 'startedYesterday':
                        return 'Gestartet: Gestern';
                    case 'startedToday':
                        return 'Gestartet: Heute';
                    case 'startedThisWeek':
                        return 'Gestartet: diese Woche';
                    case 'startedThisMonth':
                        return 'Gestartet: diesen Monat';
                    case 'startedThisYear':
                        return 'Gestartet: dieses Jahr';
                    default:
                        return 'Alle';
                }
            },
            async exportAll() {
                try {
                    const response = await fetch('/api/v1/financial-supports/export-all.zip', {
                        method: 'GET',
                        headers: {
                            'Accept': 'application/zip',
                        },
                    });
                    
                    if (!response.ok) {
                        throw new Error('Export failed');
                    }
                    
                    const blob = await response.blob();
                    const url = window.URL.createObjectURL(blob);
                    const link = document.createElement('a');
                    link.href = url;
                    link.setAttribute('download', 'financial-supports-export.zip');
                    document.body.appendChild(link);
                    link.click();
                    link.remove();
                    window.URL.revokeObjectURL(url);
                } catch (error) {
                    console.error('Error exporting financial supports:', error);
                    // You may want to show an error message to the user here
                }
            },
            showPublishDialog() {
                this.showPublishModal = true;
                this.publishStatus = null;
                this.confirmingPublish = false;
                this.confirmingEnvironment = null;
            },
            confirmPublish(environment) {
                this.confirmingEnvironment = environment;
                this.confirmingPublish = true;
            },
            cancelConfirmation() {
                this.confirmingPublish = false;
                this.confirmingEnvironment = null;
            },
            async publishToEnvironment(environment) {
                this.isPublishing = true;
                this.publishStatus = null;
                
                try {
                    const response = await fetch('/api/v1/financial-supports/publish', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                        },
                        body: JSON.stringify({ environment }),
                    });
                    
                    const result = await response.json();
                    
                    if (response.ok) {
                        this.publishStatus = {
                            success: true,
                            message: `Publikation auf ${environment === 'production' ? 'Live-Umgebung' : 'Test-Umgebung'} erfolgreich.`
                        };
                        // Reset confirmation state after successful publish
                        this.confirmingPublish = false;
                        this.confirmingEnvironment = null;
                        // Reload publication status after successful publish
                        this.loadPublicationStatus();
                    } else {
                        this.publishStatus = {
                            success: false,
                            message: `Fehler bei der Publikation: ${result.error || 'Unbekannter Fehler'}`
                        };
                    }
                } catch (error) {
                    console.error('Error publishing financial supports:', error);
                    this.publishStatus = {
                        success: false,
                        message: 'Ein Fehler ist aufgetreten. Bitte versuchen Sie es später erneut.'
                    };
                } finally {
                    this.isPublishing = false;
                }
            },
            async loadPublicationStatus() {
                try {
                    const response = await fetch('/api/v1/financial-supports/publication-status');
                    const result = await response.json();
                    
                    // Initialize status map
                    this.publicationStatusMap = {};
                    
                    // The data is now directly in result (no debug wrapper)
                    if (Array.isArray(result)) {
                        // Process the publication status for each financial support
                        result.forEach(fs => {
                            this.publicationStatusMap[fs.id] = {
                                production: fs.production ? {
                                    publishedAt: new Date(fs.production.publishedAt),
                                    publishedBy: fs.production.publishedBy
                                } : null,
                                staging: fs.staging ? {
                                    publishedAt: new Date(fs.staging.publishedAt),
                                    publishedBy: fs.staging.publishedBy
                                } : null
                            };
                        });
                    }
                    
                } catch (error) {
                    console.error('Error loading publication status:', error);
                }
            },
            getPublicationStatusClass(financialSupportId, environment) {
                const status = this.publicationStatusMap[financialSupportId];
                if (!status) return 'not-published';
                
                const envStatus = status[environment];
                const isPublished = envStatus !== null && envStatus.publishedAt !== null;
                return isPublished ? 'published' : 'not-published';
            },
            getPublicationStatusTooltip(financialSupportId, environment) {
                const status = this.publicationStatusMap[financialSupportId];
                const envLabel = environment === 'production' ? 'Live' : 'Test';
                
                if (!status) {
                    return `${envLabel}: Status wird geladen...`;
                }
                
                const envStatus = status[environment];
                
                if (envStatus && envStatus.publishedAt) {
                    return `${envLabel}: Publiziert am ${envStatus.publishedAt.toLocaleString('de-DE')} von ${envStatus.publishedBy}`;
                } else {
                    return `${envLabel}: Nicht publiziert`;
                }
            },
        },
        created () {
            this.loadFilter();
            // Start loading publication status immediately
            this.loadPublicationStatus();
            Promise.all([
                this.$store.dispatch('authorities/loadAll'),
                this.$store.dispatch('states/loadAll'),
                this.$store.dispatch('topics/loadAll'),
                this.$store.dispatch('instruments/loadAll'),
            ]).then(() => {
                this.reloadFinancialSupports();
            });
        },
    }
</script>

<style>
/* Modern Modal Styling */
.modal-modern {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    z-index: 1000;
    display: flex;
    align-items: center;
    justify-content: center;
    animation: modalFadeIn 0.3s ease-out;
}

@keyframes modalFadeIn {
    from {
        opacity: 0;
    }
    to {
        opacity: 1;
    }
}

.modal-backdrop {
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background-color: rgba(0, 0, 0, 0.6);
    backdrop-filter: blur(4px);
}

.modal-content-modern {
    background-color: #fff;
    border-radius: 12px;
    padding: 0;
    width: 600px;
    max-width: 90%;
    position: relative;
    z-index: 1001;
    box-shadow: 0 20px 40px rgba(0, 0, 0, 0.15);
    transform: scale(0.95);
    animation: modalSlideIn 0.3s ease-out forwards;
}

@keyframes modalSlideIn {
    from {
        transform: scale(0.95) translateY(-10px);
        opacity: 0;
    }
    to {
        transform: scale(1) translateY(0);
        opacity: 1;
    }
}

.modal-header-modern {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 24px 28px;
    border-bottom: 1px solid #e9ecef;
    background-color: #f8f9fa;
    border-radius: 12px 12px 0 0;
}

.modal-header-modern h3 {
    margin: 0;
    font-size: 1.25rem;
    font-weight: 600;
    color: #2d3748;
}

.close-button-modern {
    background: none;
    border: none;
    cursor: pointer;
    padding: 8px;
    border-radius: 6px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #6c757d;
    transition: all 0.2s ease;
}

.close-button-modern:hover {
    background-color: #e9ecef;
    color: #495057;
}

.modal-body-modern {
    padding: 28px;
}

.modal-description {
    font-size: 1rem;
    color: #6c757d;
    margin-bottom: 24px;
    text-align: center;
}

/* Environment Cards */
.environment-cards {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 16px;
    margin-bottom: 24px;
}

.environment-card {
    padding: 24px;
    border: 2px solid #e9ecef;
    border-radius: 12px;
    text-align: center;
    cursor: pointer;
    transition: all 0.3s ease;
    background-color: #fff;
}

.environment-card:hover:not(.disabled) {
    border-color: #E53940;
    transform: translateY(-2px);
    box-shadow: 0 8px 16px rgba(52, 152, 219, 0.15);
}

.environment-card.disabled {
    opacity: 0.6;
    cursor: not-allowed;
}

.environment-icon {
    display: flex;
    justify-content: center;
    align-items: center;
    width: 60px;
    height: 60px;
    margin: 0 auto 16px;
    border-radius: 50%;
}

.environment-icon.production {
    background-color: #e8f5e8;
    color: #2e7d32;
}

.environment-icon.staging {
    background-color: #e3f2fd;
    color: #E53940;
}

.environment-card h4 {
    margin: 0 0 8px 0;
    font-size: 1.1rem;
    font-weight: 600;
    color: #2d3748;
}

.environment-card p {
    margin: 0;
    font-size: 0.9rem;
    color: #6c757d;
}

/* Confirmation Content */
.confirmation-content {
    text-align: center;
    padding: 20px 0;
}

.confirmation-icon {
    display: flex;
    justify-content: center;
    align-items: center;
    width: 80px;
    height: 80px;
    margin: 0 auto 20px;
    border-radius: 50%;
    background-color: #e8f5e8;
    color: #2e7d32;
}

.confirmation-content h4 {
    margin: 0 0 16px 0;
    font-size: 1.2rem;
    font-weight: 600;
    color: #2d3748;
}

.confirmation-message {
    font-size: 1rem;
    color: #6c757d;
    margin-bottom: 24px;
    line-height: 1.5;
}

.confirmation-buttons-modern {
    display: flex;
    justify-content: center;
    gap: 12px;
}

/* Modern Buttons */
.button-modern {
    padding: 12px 24px;
    border: none;
    border-radius: 8px;
    font-size: 0.95rem;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.2s ease;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 100px;
}

.button-modern.primary {
    background-color: #E53940;
    color: white;
}

.button-modern.primary:hover:not(:disabled) {
    background-color: #E53940;
    transform: translateY(-1px);
}

.button-modern.secondary {
    background-color: #6c757d;
    color: white;
}

.button-modern.secondary:hover:not(:disabled) {
    background-color: #5a6268;
    transform: translateY(-1px);
}

.button-modern:disabled {
    opacity: 0.6;
    cursor: not-allowed;
    transform: none;
}

/* Modern Loading State */
.publishing-status-modern {
    text-align: center;
    padding: 40px 20px;
}

.loading-container {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 24px;
}

.modern-spinner {
    width: 50px;
    height: 50px;
    border: 4px solid #e9ecef;
    border-top: 4px solid #E53940;
    border-radius: 50%;
    animation: spin 1s linear infinite;
}

@keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}

.loading-content h4 {
    margin: 0 0 8px 0;
    font-size: 1.1rem;
    font-weight: 600;
    color: #2d3748;
}

.loading-content p {
    margin: 0 0 24px 0;
    color: #6c757d;
}

.loading-steps {
    display: flex;
    flex-direction: column;
    gap: 12px;
    text-align: left;
}

.step {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 8px 0;
    color: #6c757d;
    transition: color 0.3s ease;
}

.step.active {
    color: #E53940;
}

.step-indicator {
    width: 12px;
    height: 12px;
    border-radius: 50%;
    background-color: #e9ecef;
    transition: background-color 0.3s ease;
}

.step.active .step-indicator {
    background-color: #E53940;
    animation: pulse 1.5s infinite;
}

@keyframes pulse {
    0% { opacity: 1; }
    50% { opacity: 0.5; }
    100% { opacity: 1; }
}

/* Modern Status */
.publish-status-modern {
    display: flex;
    align-items: center;
    gap: 16px;
    padding: 20px;
    border-radius: 8px;
    margin-top: 20px;
}

.publish-status-modern.success {
    background-color: #e8f5e8;
    border: 1px solid #c8e6c9;
}

.publish-status-modern.error {
    background-color: #ffebee;
    border: 1px solid #ffcdd2;
}

.status-icon {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 48px;
    height: 48px;
    border-radius: 50%;
    flex-shrink: 0;
}

.publish-status-modern.success .status-icon {
    background-color: #c8e6c9;
    color: #2e7d32;
}

.publish-status-modern.error .status-icon {
    background-color: #ffcdd2;
    color: #c62828;
}

.status-content h4 {
    margin: 0 0 4px 0;
    font-size: 1.1rem;
    font-weight: 600;
}

.publish-status-modern.success .status-content h4 {
    color: #2e7d32;
}

.publish-status-modern.error .status-content h4 {
    color: #c62828;
}

.status-content p {
    margin: 0;
    color: #6c757d;
}

/* Mobile Responsiveness */
@media (max-width: 768px) {
    .modal-content-modern {
        width: 95%;
        margin: 20px;
    }
    
    .environment-cards {
        grid-template-columns: 1fr;
        gap: 12px;
    }
    
    .confirmation-buttons-modern {
        flex-direction: column;
        gap: 8px;
    }
    
    .button-modern {
        width: 100%;
    }
    
    .publish-status-modern {
        flex-direction: column;
        text-align: center;
        gap: 12px;
    }
}

.publication-status-dots {
    display: flex;
    gap: 15px;
    align-items: center;
    justify-content: center;
}

.status-dot-container {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 3px;
}

.status-dot {
    width: 12px;
    height: 12px;
    border-radius: 50%;
    cursor: help;
    transition: transform 0.2s ease;
}

.status-dot:hover {
    transform: scale(1.2);
}

.status-dot.published {
    background-color: #28a745;
    box-shadow: 0 0 4px rgba(40, 167, 69, 0.4);
}

.status-dot.not-published {
    background-color: #dc3545;
    box-shadow: 0 0 4px rgba(220, 53, 69, 0.4);
}

.status-label {
    font-size: 10px;
    color: #666;
    font-weight: 500;
    text-transform: uppercase;
}
</style>