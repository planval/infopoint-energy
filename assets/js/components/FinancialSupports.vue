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
                        <label for="authority">Förderstelle</label>
                        <div class="select-wrapper">
                            <select id="authority" class="form-control" @change="addFilter({type: 'authority', value: $event.target.value}); $event.target.value = null;">
                                <option></option>
                                <option v-for="authority in authorities.filter(authority => !authority.context || authority.context === 'financial-support')" :value="authority.name">{{authority.name}}</option>
                            </select>
                        </div>
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
                    <strong v-if="filter.type === 'authority'">Förderstelle:</strong>
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
                return this.$store.dispatch('financialSupports/loadFiltered', this.getFilterParams()).then(() => {
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
                    const response = await fetch('/api/v1/financial-supports/export-all-zip', {
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
        },
        created () {
            this.loadFilter();
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