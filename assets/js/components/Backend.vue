<template>

    <div class="backend-component">

        <div class="backend-component-loader" :class="{visible: isLoading}">
            <div class="backend-component-loader-text">
                {{ loadingProgress }} / {{ loadingTotal }} Komponenten geladen...
            </div>
        </div>

        <div class="backend-component-sidebar" :class="{'is-collapsed': isSidebarCollapsed}">

            <div class="backend-component-sidebar-header">
                <a href="/">
                    <transition name="fade" mode="out-in">
                        <img :src="$env.THEME_ICON" alt="">
                    </transition>
                </a>
            </div>

            <ul>
                <li v-if="hasRole('ROLE_EDITOR') && $env.PLUGIN_ENABLE_INBOX">
                    <router-link to="/inbox">
                        <span class="material-icons">inbox</span>
                        <span class="label">Posteingang</span>
                    </router-link>
                </li>
                <li v-if="hasRole('ROLE_EDITOR') && $env.PLUGIN_ENABLE_PROJECTS">
                    <router-link to="/projects" :class="{'router-link-parent-active': this.$route.path.startsWith('/project')}">
                        <span class="material-icons">category</span>
                        <span class="label">Projekte</span>
                    </router-link>
                    <ul>
                        <li>
                            <router-link to="/projects">
                                <span class="material-icons">list</span>
                                <span class="label">Liste anzeigen</span>
                            </router-link>
                        </li>
                        <li>
                            <router-link to="/projects/add">
                                <span class="material-icons">create</span>
                                <span class="label">Neues Projekt erfassen</span>
                            </router-link>
                        </li>
                        <li v-if="$env.PLUGIN_ENABLE_PROJECT_COLLECTIONS">
                            <router-link to="/project-collections">
                                <span class="material-icons">dashboard</span>
                                <span class="label">Projektkollektionen</span>
                            </router-link>
                        </li>
                    </ul>
                </li>
                <li v-if="hasRole('ROLE_EDITOR') && $env.PLUGIN_ENABLE_EVENTS">
                    <router-link to="/events" :class="{'router-link-parent-active': this.$route.path.startsWith('/event')}">
                        <span class="material-icons">event</span>
                        <span class="label">Agenda</span>
                    </router-link>
                    <ul>
                        <li>
                            <router-link to="/events">
                                <span class="material-icons">list</span>
                                <span class="label">Liste anzeigen</span>
                            </router-link>
                        </li>
                        <li>
                            <router-link to="/events/add">
                                <span class="material-icons">create</span>
                                <span class="label">Neuen Eintrag erfassen</span>
                            </router-link>
                        </li>
                        <li v-if="$env.PLUGIN_ENABLE_EVENT_COLLECTIONS">
                            <router-link to="/event-collections">
                                <span class="material-icons">dashboard</span>
                                <span class="label">Agenda-Kollektionen</span>
                            </router-link>
                        </li>
                    </ul>
                </li>
                <li v-if="hasRole('ROLE_EDITOR') && $env.PLUGIN_ENABLE_POSTS">
                    <router-link to="/posts" :class="{'router-link-parent-active': this.$route.path.startsWith('/post')}">
                        <span class="material-icons">newspaper</span>
                        <span class="label">News</span>
                    </router-link>
                    <ul>
                        <li>
                            <router-link to="/posts">
                                <span class="material-icons">list</span>
                                <span class="label">Liste anzeigen</span>
                            </router-link>
                        </li>
                        <li>
                            <router-link to="/posts/add">
                                <span class="material-icons">create</span>
                                <span class="label">Neuen Eintrag erfassen</span>
                            </router-link>
                        </li>
                    </ul>
                </li>
                <li v-if="hasRole('ROLE_EDITOR') && $env.PLUGIN_ENABLE_INTERACTIVE_GRAPHICS">
                    <router-link to="/interactive-graphics">
                        <span class="material-icons">image</span>
                        <span class="label">Interaktive Grafiken</span>
                    </router-link>
                </li>
                <li v-if="hasRole('ROLE_EDITOR') && $env.PLUGIN_ENABLE_FINANCIAL_SUPPORTS">
                    <router-link to="/financial-supports" :class="{'router-link-parent-active': this.$route.path.startsWith('/financial-support')}">
                        <span class="material-icons">account_balance</span>
                        <span class="label">Förderhilfen</span>
                    </router-link>
                    <ul>
                        <li>
                            <router-link to="/financial-supports">
                                <span class="material-icons">list</span>
                                <span class="label">Liste anzeigen</span>
                            </router-link>
                        </li>
                        <li>
                            <router-link to="/financial-supports/add">
                                <span class="material-icons">create</span>
                                <span class="label">Neuen Eintrag erfassen</span>
                            </router-link>
                        </li>
                        <li>
                            <router-link to="/financial-supports/contacts">
                                <span class="material-icons">contacts</span>
                                <span class="label">Kontakte</span>
                            </router-link>
                        </li>
                    </ul>
                </li>
                <li v-if="hasRole('ROLE_EDITOR') && $env.PLUGIN_ENABLE_CONTACTS">
                    <router-link to="/contacts/person" :class="{'router-link-parent-active': this.$route.path.startsWith('/contacts/person')}">
                        <span class="material-icons">person</span>
                        <span class="label">Personen</span>
                    </router-link>
                    <ul>
                        <li>
                            <router-link to="/contacts/person">
                                <span class="material-icons">list</span>
                                <span class="label">Liste anzeigen</span>
                            </router-link>
                        </li>
                        <li>
                            <router-link to="/contacts/person/add">
                                <span class="material-icons">create</span>
                                <span class="label">Neuen Eintrag erfassen</span>
                            </router-link>
                        </li>
                    </ul>
                </li>
                <li v-if="hasRole('ROLE_EDITOR') && $env.PLUGIN_ENABLE_CONTACTS">
                    <router-link to="/contacts/company" :class="{'router-link-parent-active': this.$route.path.startsWith('/contacts/company')}">
                        <span class="material-icons">business</span>
                        <span class="label">Organisationen</span>
                    </router-link>
                    <ul>
                        <li>
                            <router-link to="/contacts/company">
                                <span class="material-icons">list</span>
                                <span class="label">Liste anzeigen</span>
                            </router-link>
                        </li>
                        <li>
                            <router-link to="/contacts/company/add">
                                <span class="material-icons">create</span>
                                <span class="label">Neuen Eintrag erfassen</span>
                            </router-link>
                        </li>
                    </ul>
                </li>
                <li v-if="hasRole('ROLE_EDITOR') && $env.PLUGIN_ENABLE_CONTACT_GROUPS">
                    <router-link to="/contact-groups" :class="{'router-link-parent-active': this.$route.path.startsWith('/contact-groups')}">
                        <span class="material-icons">group</span>
                        <span class="label">Kontaktgruppen</span>
                    </router-link>
                    <ul>
                        <li>
                            <router-link to="/contact-groups">
                                <span class="material-icons">list</span>
                                <span class="label">Liste anzeigen</span>
                            </router-link>
                        </li>
                        <li>
                            <router-link to="/contact-groups/add">
                                <span class="material-icons">create</span>
                                <span class="label">Neuen Eintrag erfassen</span>
                            </router-link>
                        </li>
                    </ul>
                </li>
                <li v-if="hasRole('ROLE_EDITOR') && $env.PLUGIN_ENABLE_REGIONS">
                    <router-link to="/regions" :class="{'router-link-parent-active': this.$route.path.startsWith('/regions')}">
                        <span class="material-icons">place</span>
                        <span class="label">Regionen</span>
                    </router-link>
                    <ul>
                        <li>
                            <router-link to="/regions">
                                <span class="material-icons">list</span>
                                <span class="label">Liste anzeigen</span>
                            </router-link>
                        </li>
                        <li>
                            <router-link to="/regions/add">
                                <span class="material-icons">create</span>
                                <span class="label">Neuen Eintrag erfassen</span>
                            </router-link>
                        </li>
                    </ul>
                </li>
                <li v-if="hasRole('ROLE_EDITOR') && $env.PLUGIN_ENABLE_EDUCATIONS">
                    <router-link to="/educations" :class="{'router-link-parent-active': this.$route.path.startsWith('/educations')}">
                        <span class="material-icons">school</span>
                        <span class="label">Bildungsmöglichkeiten</span>
                    </router-link>
                    <ul>
                        <li>
                            <router-link to="/educations">
                                <span class="material-icons">list</span>
                                <span class="label">Liste anzeigen</span>
                            </router-link>
                        </li>
                        <li>
                            <router-link to="/educations/add">
                                <span class="material-icons">create</span>
                                <span class="label">Neuen Eintrag erfassen</span>
                            </router-link>
                        </li>
                    </ul>
                </li>
                <li v-if="hasRole('ROLE_EDITOR') && $env.PLUGIN_ENABLE_JOBS">
                    <router-link to="/jobs" :class="{'router-link-parent-active': this.$route.path.startsWith('/jobs')}">
                        <span class="material-icons">work</span>
                        <span class="label">Stellenmarkt</span>
                    </router-link>
                    <ul>
                        <li>
                            <router-link to="/jobs">
                                <span class="material-icons">list</span>
                                <span class="label">Liste anzeigen</span>
                            </router-link>
                        </li>
                        <li>
                            <router-link to="/jobs/add">
                                <span class="material-icons">create</span>
                                <span class="label">Neuen Eintrag erfassen</span>
                            </router-link>
                        </li>
                    </ul>
                </li>
                <li v-if="hasRole('ROLE_ADMIN')">
                    <router-link to="/settings/users" :class="{'router-link-parent-active': this.$route.path.startsWith('/settings')}">
                        <span class="material-icons">settings</span>
                        <span class="label">Administration</span>
                    </router-link>
                    <ul>
                        <li>
                            <router-link to="/settings/users">
                                <span class="material-icons">group</span>
                                <span class="label">Benutzerverwaltung</span>
                            </router-link>
                        </li>
                    </ul>
                </li>
            </ul>

            <ul>
                <li>
                    <a @click="clickToggleSidebar()">
                        <span class="material-icons">
                            <template v-if="isSidebarCollapsed">keyboard_double_arrow_right</template>
                            <template v-else>keyboard_double_arrow_left</template>
                        </span>
                        <span class="label">
                            <template v-if="isSidebarCollapsed">Menü einblenden</template>
                            <template v-else>Menü ausblenden</template>
                        </span>
                    </a>
                </li>
                <li>
                    <a class="error" href="/logout">
                        <span class="material-icons">logout</span>
                        <span class="label">Abmelden</span>
                    </a>
                </li>
            </ul>

        </div>

        <div class="backend-component-content" :class="{'is-collapsed': !isSidebarCollapsed}">

            <router-view v-slot="{ Component }">
                <transition name="fade" mode="out-in">
                    <component :key="$route.path" :is="Component"></component>
                </transition>
            </router-view>

        </div>

    </div>

</template>

<script>
import {mapGetters, mapState} from 'vuex';

    export default {

        data() {
            return {
                loadingProgress: 0,
                loadingTotal: 0,
                isLoading: false,
            };
        },

        computed: {
            ...mapState({
                isSidebarCollapsed: state => state.ui.isSidebarCollapsed,
            }),
            ...mapGetters({
                hasRole: 'users/hasRole',
            }),
        },

        methods: {

            reload() {
                this.isLoading = true;
                this.loadingProgress = 0;
                this.loadingTotal = 19;

                Promise.all(
                    [
                        this.$store.dispatch('users/loadMe').then(() => this.loadingProgress += 1),
                        this.$store.dispatch('languages/loadAll').then(() => this.loadingProgress += 1),
                        this.$store.dispatch('locations/loadAll').then(() => this.loadingProgress += 1),
                        this.$store.dispatch('educationTypes/loadAll').then(() => this.loadingProgress += 1),
                        this.$store.dispatch('programs/loadAll').then(() => this.loadingProgress += 1),
                        this.$store.dispatch('instruments/loadAll').then(() => this.loadingProgress += 1),
                        this.$store.dispatch('topics/loadAll').then(() => this.loadingProgress += 1),
                        this.$store.dispatch('countries/loadAll').then(() => this.loadingProgress += 1),
                        this.$store.dispatch('states/loadAll').then(() => this.loadingProgress += 1),
                        this.$store.dispatch('geographicRegions/loadAll').then(() => this.loadingProgress += 1),
                        this.$store.dispatch('businessSectors/loadAll').then(() => this.loadingProgress += 1),
                        this.$store.dispatch('authorities/loadAll').then(() => this.loadingProgress += 1),
                        this.$store.dispatch('beneficiaries/loadAll').then(() => this.loadingProgress += 1),
                        this.$store.dispatch('projectTypes/loadAll').then(() => this.loadingProgress += 1),
                        this.$store.dispatch('cities/loadAll').then(() => this.loadingProgress += 1),
                        this.$store.dispatch('contactGroups/loadAll').then(() => this.loadingProgress += 1),
                        this.$store.dispatch('stints/loadAll').then(() => this.loadingProgress += 1),
                        this.$store.dispatch('tags/loadAll').then(() => this.loadingProgress += 1),
                    ]
                ).then(() => {
                    this.isLoading = false;
                })
            },

            clickToggleSidebar() {
                this.$store.commit('ui/setIsSidebarCollapsed', !this.isSidebarCollapsed);
            },
        },

        created () {
            this.reload();
        },
    }
</script>