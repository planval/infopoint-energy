import { createRouter, createWebHashHistory } from 'vue-router';

import Backend from '../components/Backend';
import Inbox from '../components/Inbox';
import Project from '../components/Project';
import Projects from '../components/Projects';
import ProjectCollections from '../components/ProjectCollections';
import ProjectCollection from '../components/ProjectCollection';
import InteractiveGraphics from '../components/InteractiveGraphics';
import InteractiveGraphic from '../components/InteractiveGraphic';
import Events from '../components/Events';
import Event from '../components/Event';
import EventCollections from '../components/EventCollections';
import EventCollection from '../components/EventCollection';
import Users from '../components/Users';
import User from '../components/User';
import FinancialSupports from '../components/FinancialSupports';
import FinancialSupport from '../components/FinancialSupport';
import FinancialSupportContacts from '../components/FinancialSupportContacts';
import Educations from '../components/Educations';
import Education from '../components/Education';
import Jobs from '../components/Jobs';
import Job from '../components/Job';
import ContactsPersons from '../components/ContactsPersons';
import ContactPerson from '../components/ContactPerson';
import ContactsCompanies from '../components/ContactsCompanies';
import ContactCompany from '../components/ContactCompany';
import ContactGroups from '../components/ContactGroups';
import ContactGroup from '../components/ContactGroup';
import Regions from '../components/Regions';
import Region from '../components/Region';
import Posts from '../components/Posts';
import Post from '../components/Post';

const routes = [
    {
        path: '/',
        component: Backend,
        children: [
            {
                path: '',
                redirect: '/financial-supports',
            },
            {
                path: 'inbox',
                name: 'inbox',
                component: Inbox,
            },
            {
                path: '/inbox/contacts/:type/:id/:inbox_id',
                component: ContactPerson,
                meta: {
                    requiresAuth: true,
                },
            },
            {
                path: 'inbox/projects/:id',
                name: 'inbox_project',
                component: Project,
            },
            {
                path: 'projects/add',
                name: 'projects_add',
                component: Project,
            },
            {
                path: 'projects/:id/edit',
                name: 'projects_edit',
                component: Project,
            },
            {
                path: 'projects',
                name: 'projects',
                component: Projects,
            },
            {
                path: 'project-collections',
                name: 'project-collections',
                component: ProjectCollections,
            },
            {
                path: 'project-collections/add',
                name: 'project-collections_add',
                component: ProjectCollection,
            },
            {
                path: 'project-collections/:id/edit',
                name: 'project-collections_edit',
                component: ProjectCollection,
            },
            {
                path: 'interactive-graphics',
                name: 'interactive-graphics',
                component: InteractiveGraphics,
            },
            {
                path: 'interactive-graphics/add',
                name: 'interactive-graphics_add',
                component: InteractiveGraphic,
            },
            {
                path: 'interactive-graphics/:id/edit',
                name: 'interactive-graphics_edit',
                component: InteractiveGraphic,
            },
            {
                path: 'events',
                name: 'events',
                component: Events,
            },
            {
                path: 'events/add',
                name: 'events_add',
                component: Event,
            },
            {
                path: 'events/:id/edit',
                name: 'events_edit',
                component: Event,
            },
            {
                path: 'event-collections',
                name: 'event-collections',
                component: EventCollections,
            },
            {
                path: 'event-collections/add',
                name: 'event-collections_add',
                component: EventCollection,
            },
            {
                path: 'event-collections/:id/edit',
                name: 'event-collections_edit',
                component: EventCollection,
            },
            {
                path: 'posts',
                name: 'posts',
                component: Posts,
            },
            {
                path: 'posts/add',
                name: 'posts_add',
                component: Post,
            },
            {
                path: 'posts/:id/edit',
                name: 'posts_edit',
                component: Post,
            },
            {
                path: 'financial-supports',
                name: 'financial-supports',
                component: FinancialSupports,
            },
            {
                path: 'financial-supports/add',
                name: 'financial-supports_add',
                component: FinancialSupport,
            },
            {
                path: 'financial-supports/:id/edit',
                name: 'financial-supports_edit',
                component: FinancialSupport,
            },
            {
                path: 'financial-supports/contacts',
                name: 'financial-supports_contacts',
                component: FinancialSupportContacts,
            },
            {
                path: 'contacts/company',
                name: 'contacts/company',
                component: ContactsCompanies,
            },
            {
                path: 'contacts/company/add',
                name: 'contacts_company_add',
                component: ContactCompany,
            },
            {
                path: 'contacts/company/:id/edit',
                name: 'contacts_company_edit',
                component: ContactCompany,
            },
            {
                path: 'contacts/person',
                name: 'contacts/person',
                component: ContactsPersons,
            },
            {
                path: 'contacts/person/add',
                name: 'contacts_person_add',
                component: ContactPerson,
            },
            {
                path: 'contacts/person/:id/edit',
                name: 'contacts_person_edit',
                component: ContactPerson,
            },
            {
                path: 'contact-groups',
                name: 'contact_groups',
                component: ContactGroups,
            },
            {
                path: 'contact-groups/add',
                name: 'contact_groups_add',
                component: ContactGroup,
            },
            {
                path: 'contact-groups/:id/edit',
                name: 'contact_groups_edit',
                component: ContactGroup,
            },
            {
                path: 'regions',
                name: 'regions',
                component: Regions,
            },
            {
                path: 'regions/add',
                name: 'regions_add',
                component: Region,
            },
            {
                path: 'regions/:id/edit',
                name: 'regions_edit',
                component: Region,
            },
            {
                path: 'educations',
                name: 'educations',
                component: Educations,
            },
            {
                path: 'educations/add',
                name: 'educations_add',
                component: Education,
            },
            {
                path: 'educations/:id/edit',
                name: 'educations_edit',
                component: Education,
            },
            {
                path: 'jobs',
                name: 'jobs',
                component: Jobs,
            },
            {
                path: 'jobs/add',
                name: 'jobs_add',
                component: Job,
            },
            {
                path: 'jobs/:id/edit',
                name: 'jobs_edit',
                component: Job,
            },
            {
                path: 'settings/users',
                name: 'users',
                component: Users,
            },
            {
                path: 'settings/users/add',
                name: 'users_add',
                component: User,
            },
            {
                path: 'settings/users/:id/edit',
                name: 'users_edit',
                component: User,
            },
        ]
    }
];

const router = createRouter({
    routes,
    history: createWebHashHistory()
});

export default router;