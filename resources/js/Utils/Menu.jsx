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
    IconFileAnalytics,
    IconGauge,
    IconHierarchy3,
    IconPercentage,
    IconPlugConnected,
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
            title: 'DASHBOARD',
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
            title: 'TRANSACTION',
            permissions: accountingAccess,
            details: [
                {
                    title: 'Manual Journal',
                    href: '/apps/manual-journals',
                    active: url.startsWith('/apps/manual-journals') && !url.startsWith('/apps/manual-journals/integration-journal'),
                    icon: <IconClipboardText size={20} strokeWidth={1.5} />,
                    permissions: accountingAccess,
                },
                {
                    title: 'Integration Journal',
                    href: '/apps/manual-journals/integration-journal',
                    active: url.startsWith('/apps/manual-journals/integration-journal'),
                    icon: <IconAdjustments size={20} strokeWidth={1.5} />,
                    permissions: accountingAccess,
                },
                {
                    title: 'Integration Event',
                    href: '/apps/integration-events',
                    active: url.startsWith('/apps/integration-events'),
                    icon: <IconFileAnalytics size={20} strokeWidth={1.5} />,
                    permissions: accountingAccess,
                },
                {
                    title: 'Recurring & Reversal',
                    href: '#recurring-journal',
                    active: false,
                    icon: <IconArrowsTransferDown size={20} strokeWidth={1.5} />,
                    permissions: accountingAccess,
                },
            ],
        },
        {
            title: 'LAPORAN KEUANGAN',
            permissions: accountingAccess,
            details: [
                {
                    title: 'Neraca',
                    href: '/apps/reports/balance-sheet',
                    active: url.startsWith('/apps/reports/balance-sheet'),
                    icon: <IconChartBar size={20} strokeWidth={1.5} />,
                    permissions: accountingAccess,
                },
                {
                    title: 'Rugi Laba',
                    href: '/apps/reports/profit-loss',
                    active: url.startsWith('/apps/reports/profit-loss'),
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
                    href: '/apps/reports/indirect-cash-flow',
                    active: url.startsWith('/apps/reports/indirect-cash-flow'),
                    icon: <IconChartBar size={20} strokeWidth={1.5} />,
                    permissions: accountingAccess,
                },
                {
                    title: 'Trial Balance',
                    href: '/apps/reports/trial-balance',
                    active: url.startsWith('/apps/reports/trial-balance'),
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
        {
            title: 'SETUP',
            permissions: accountingAccess,
            details: [
                {
                    title: 'Preset Jurnal',
                    href: '/apps/setup/preset-journals',
                    active: url.startsWith('/apps/setup/preset-journals'),
                    icon: <IconSettings size={20} strokeWidth={1.5} />,
                    permissions: accountingAccess,
                },
            ],
        },
        {
            title: 'INTEGRATION MODUL',
            permissions: accountingAccess,
            details: [
                {
                    title: 'Client Secret',
                    href: '/apps/integration/client-secrets',
                    active: url.startsWith('/apps/integration/client-secrets'),
                    icon: <IconPlugConnected size={20} strokeWidth={1.5} />,
                    permissions: accountingAccess,
                },
                {
                    title: 'Posting Rules',
                    href: '/apps/integration/posting-rules',
                    active: url.startsWith('/apps/integration/posting-rules'),
                    icon: <IconAdjustments size={20} strokeWidth={1.5} />,
                    permissions: accountingAccess,
                },
            ],
        },
        {
            title: 'MASTER DATA',
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
                    title: 'Saldo Awal',
                    href: '/apps/opening-balances',
                    active: url.startsWith('/apps/opening-balances'),
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
            title: 'USER MANAGEMENT',
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
    ];

    return menuNavigation;
}
