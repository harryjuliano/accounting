import { usePage } from '@inertiajs/react';
import {
    IconAdjustments,
    IconArrowsTransferDown,
    IconBook2,
    IconBooks,
    IconCalendarStats,
    IconChartBar,
    IconChecklist,
    IconClipboardText,
    IconCurrencyDollar,
    IconGauge,
    IconHierarchy3,
    IconLock,
    IconPercentage,
    IconReportMoney,
    IconSettings,
    IconShieldCheck,
    IconUserCog,
    IconUsers,
} from '@tabler/icons-react';
import hasAnyPermission from './Permissions';

export default function Menu() {
    const { url } = usePage();

    const accountingAccess = hasAnyPermission(['dashboard-access']);
    const userManagementAccess = hasAnyPermission(['users-access', 'roles-access', 'permissions-access']);

    const menuNavigation = [
        {
            title: 'User Management',
            permissions: userManagementAccess,
            details: [
                {
                    title: 'Users',
                    href: '/apps/users',
                    active: url.startsWith('/apps/users'),
                    icon: <IconUsers size={20} strokeWidth={1.5} />,
                    permissions: hasAnyPermission(['users-access']),
                },
                {
                    title: 'Roles',
                    href: '/apps/roles',
                    active: url.startsWith('/apps/roles'),
                    icon: <IconUserCog size={20} strokeWidth={1.5} />,
                    permissions: hasAnyPermission(['roles-access']),
                },
                {
                    title: 'Permissions',
                    href: '/apps/permissions',
                    active: url.startsWith('/apps/permissions'),
                    icon: <IconShieldCheck size={20} strokeWidth={1.5} />,
                    permissions: hasAnyPermission(['permissions-access']),
                },
            ],
        },
        {
            title: 'Dashboard',
            permissions: accountingAccess,
            details: [
                {
                    title: 'Finance Dashboard',
                    href: '/apps/dashboard',
                    active: url.startsWith('/apps/dashboard'),
                    icon: <IconGauge size={20} strokeWidth={1.5} />,
                    permissions: accountingAccess,
                },
                {
                    title: 'Closing Status',
                    href: '#closing-status',
                    active: false,
                    icon: <IconChecklist size={20} strokeWidth={1.5} />,
                    permissions: accountingAccess,
                },
            ],
        },
        {
            title: 'Master Data',
            permissions: accountingAccess,
            details: [
                {
                    title: 'Company',
                    href: '/apps/companies',
                    active: url.startsWith('/apps/companies'),
                    icon: <IconCalendarStats size={20} strokeWidth={1.5} />,
                    permissions: accountingAccess,
                },
                {
                    title: 'Fiscal Period',
                    href: '/apps/fiscal-periods',
                    active: url.startsWith('/apps/fiscal-periods'),
                    icon: <IconCalendarStats size={20} strokeWidth={1.5} />,
                    permissions: accountingAccess,
                },
                {
                    title: 'Branches',
                    href: '/apps/branches',
                    active: url.startsWith('/apps/branches'),
                    icon: <IconHierarchy3 size={20} strokeWidth={1.5} />,
                    permissions: accountingAccess,
                },
                {
                    title: 'Master Chart of Account',
                    href: '/apps/chart-of-accounts?type=master',
                    active: url.startsWith('/apps/chart-of-accounts') && !url.includes('type=transaction'),
                    icon: <IconBook2 size={20} strokeWidth={1.5} />,
                    permissions: accountingAccess,
                },
                {
                    title: 'Chart of Account',
                    href: '/apps/chart-of-accounts?type=transaction',
                    active: url.startsWith('/apps/chart-of-accounts') && url.includes('type=transaction'),
                    icon: <IconBook2 size={20} strokeWidth={1.5} />,
                    permissions: accountingAccess,
                },
                {
                    title: 'Dimensions',
                    href: '/apps/dimensions',
                    active: url.startsWith('/apps/dimensions'),
                    icon: <IconHierarchy3 size={20} strokeWidth={1.5} />,
                    permissions: accountingAccess,
                },
                {
                    title: 'Tax Codes',
                    href: '/apps/tax-codes',
                    active: url.startsWith('/apps/tax-codes'),
                    icon: <IconPercentage size={20} strokeWidth={1.5} />,
                    permissions: accountingAccess,
                },
                {
                    title: 'Currencies & Rates',
                    href: '/apps/currencies',
                    active: url.startsWith('/apps/currencies'),
                    icon: <IconCurrencyDollar size={20} strokeWidth={1.5} />,
                    permissions: accountingAccess,
                },
            ],
        },
        {
            title: 'Transactions',
            permissions: accountingAccess,
            details: [
                {
                    title: 'Manual Journal',
                    href: '/apps/manual-journals',
                    active: url.startsWith('/apps/manual-journals'),
                    icon: <IconClipboardText size={20} strokeWidth={1.5} />,
                    permissions: accountingAccess,
                },
                {
                    title: 'Recurring & Reversal',
                    href: '#recurring-journal',
                    active: false,
                    icon: <IconArrowsTransferDown size={20} strokeWidth={1.5} />,
                    permissions: accountingAccess,
                },
                {
                    title: 'Subledger Integration',
                    href: '#subledger-integration',
                    active: false,
                    icon: <IconAdjustments size={20} strokeWidth={1.5} />,
                    permissions: accountingAccess,
                },
            ],
        },
        {
            title: 'General Ledger & Closing',
            permissions: accountingAccess,
            details: [
                {
                    title: 'Journal Register',
                    href: '#journal-register',
                    active: false,
                    icon: <IconBooks size={20} strokeWidth={1.5} />,
                    permissions: accountingAccess,
                },
                {
                    title: 'Trial Balance',
                    href: '#trial-balance',
                    active: false,
                    icon: <IconChartBar size={20} strokeWidth={1.5} />,
                    permissions: accountingAccess,
                },
                {
                    title: 'Period Lock',
                    href: '#period-lock',
                    active: false,
                    icon: <IconLock size={20} strokeWidth={1.5} />,
                    permissions: accountingAccess,
                },
            ],
        },
        {
            title: 'Reports & Governance',
            permissions: accountingAccess,
            details: [
                {
                    title: 'Financial Statements',
                    href: '#financial-statements',
                    active: false,
                    icon: <IconReportMoney size={20} strokeWidth={1.5} />,
                    permissions: accountingAccess,
                },
                {
                    title: 'Audit Trail',
                    href: '#audit-trail',
                    active: false,
                    icon: <IconShieldCheck size={20} strokeWidth={1.5} />,
                    permissions: accountingAccess,
                },
                {
                    title: 'Integration Settings',
                    href: '#integration-settings',
                    active: false,
                    icon: <IconSettings size={20} strokeWidth={1.5} />,
                    permissions: accountingAccess,
                },
            ],
        },
        {
            title: 'Laporan Keuangan',
            permissions: accountingAccess,
            details: [
                {
                    title: 'Neraca',
                    href: '#neraca',
                    active: false,
                    icon: <IconChartBar size={20} strokeWidth={1.5} />,
                    permissions: accountingAccess,
                },
                {
                    title: 'Rugi Laba',
                    href: '#rugi-laba',
                    active: false,
                    icon: <IconChartBar size={20} strokeWidth={1.5} />,
                    permissions: accountingAccess,
                },
                {
                    title: 'Perubahan Modal',
                    href: '#perubahan-modal',
                    active: false,
                    icon: <IconChartBar size={20} strokeWidth={1.5} />,
                    permissions: accountingAccess,
                },
                {
                    title: 'Arus Kas Tidak Langsung',
                    href: '#arus-kas-tidak-langsung',
                    active: false,
                    icon: <IconChartBar size={20} strokeWidth={1.5} />,
                    permissions: accountingAccess,
                },
                {
                    title: 'Trial Balance',
                    href: '#trial-balance-financial',
                    active: false,
                    icon: <IconChartBar size={20} strokeWidth={1.5} />,
                    permissions: accountingAccess,
                },
                {
                    title: 'General Ledger',
                    href: '/apps/reports/general-ledger',
                    active: url.startsWith('/apps/reports/general-ledger'),
                    icon: <IconBooks size={20} strokeWidth={1.5} />,
                    permissions: accountingAccess,
                },
            ],
        },
    ];

    return menuNavigation;
}
