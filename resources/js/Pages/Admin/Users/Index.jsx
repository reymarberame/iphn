import React, { useEffect, useMemo, useState } from 'react';
import { Head, useForm, router } from '@inertiajs/react';
import {
    Building2,
    FilterX,
    KeyRound,
    MapPin,
    Pencil,
    Search,
    ShieldCheck,
    SlidersHorizontal,
    UserPlus,
    UserRound,
    Users,
} from 'lucide-react';
import AppLayout from '../../../Layouts/AppLayout';
import {
    Avatar,
    Badge,
    Button,
    Card,
    EmptyState,
    Field,
    InfoCallout,
    Modal,
    PageHeader,
    Pagination,
    Select,
    StatCard,
    TextInput,
} from '../../../components/ui';
import { classNames, formatDateTime, formatNumber, humanize, relativeTime, toneForUserStatus } from '../../../lib/utils';

const STATUS_OPTIONS = [
    { value: 'active', label: 'Active' },
    { value: 'suspended', label: 'Suspended' },
    { value: 'inactive', label: 'Inactive' },
];

const SCOPE_TONE = {
    network: 'brand',
    region: 'violet',
    province: 'sky',
    hospital: 'teal',
};

const EMPTY_FORM = {
    name: '',
    email: '',
    phone: '',
    password: '',
    password_confirmation: '',
    status: 'active',
    role_id: '',
    hospital_id: '',
    region_id: '',
    province_id: '',
};

export default function AdminUserIndex({
    users,
    filters = {},
    roles = [],
    hospitals = [],
    regions = [],
    provinces = [],
    isNetworkUser = false,
    isSuperAdmin = false,
}) {
    const [values, setValues] = useState({
        search: filters.search ?? '',
        status: filters.status ?? '',
        role_id: filters.role_id ?? '',
    });
    const [modal, setModal] = useState(null);
    const [editing, setEditing] = useState(null);

    const form = useForm({ ...EMPTY_FORM });

    useEffect(() => {
        setValues({
            search: filters.search ?? '',
            status: filters.status ?? '',
            role_id: filters.role_id ?? '',
        });
    }, [filters.search, filters.status, filters.role_id]);

    const selectedRole = roles.find((role) => String(role.id) === String(form.data.role_id));

    const provinceGroups = useMemo(() => {
        const regionNames = new Map(regions.map((region) => [region.id, region.short_name || region.name]));
        const groups = new Map();

        provinces.forEach((province) => {
            const label = regionNames.get(province.region_id) ?? 'Other';
            if (!groups.has(label)) groups.set(label, []);
            groups.get(label).push(province);
        });

        return Array.from(groups.entries()).sort(([a], [b]) => a.localeCompare(b));
    }, [provinces, regions]);

    const go = (nextValues) => {
        const params = Object.fromEntries(Object.entries(nextValues).filter(([, value]) => value !== '' && value !== null));
        router.get('/admin/users', params, { preserveState: true, preserveScroll: true, replace: true });
    };

    const applyFilters = (event) => {
        event.preventDefault();
        go(values);
    };

    const resetFilters = () => {
        setValues({ search: '', status: '', role_id: '' });
        router.get('/admin/users', {}, { preserveState: true, preserveScroll: true, replace: true });
    };

    const defaultRole = () => roles[0]?.id ?? '';

    const openCreate = () => {
        form.clearErrors();
        const roleId = defaultRole();
        const role = roles.find((item) => item.id === roleId);

        form.setData({
            ...EMPTY_FORM,
            role_id: roleId,
            hospital_id: role?.scope_type === 'hospital' ? (hospitals[0]?.id ?? '') : '',
        });
        setEditing(null);
        setModal('create');
    };

    const openEdit = (user) => {
        form.clearErrors();
        const globalRole = user.global_roles?.[0];
        const membership = user.memberships?.[0];
        const scope = user.admin_scopes?.[0];

        form.setData({
            ...EMPTY_FORM,
            name: user.name ?? '',
            email: user.email ?? '',
            phone: user.phone ?? '',
            status: user.status ?? 'active',
            role_id: globalRole?.id ?? membership?.role_id ?? '',
            hospital_id: membership?.hospital_id ?? '',
            region_id: scope?.region_id ?? '',
            province_id: scope?.province_id ?? '',
        });
        setEditing(user);
        setModal('edit');
    };

    const closeModal = () => {
        setModal(null);
        setEditing(null);
        form.clearErrors();
    };

    const changeRole = (roleId) => {
        const role = roles.find((item) => String(item.id) === String(roleId));

        form.setData({
            ...form.data,
            role_id: roleId,
            hospital_id: role?.scope_type === 'hospital' ? form.data.hospital_id || hospitals[0]?.id || '' : '',
            region_id: role?.scope_type === 'region' ? form.data.region_id : '',
            province_id: role?.scope_type === 'province' ? form.data.province_id : '',
        });
    };

    const submit = (event) => {
        event.preventDefault();

        if (modal === 'edit' && editing) {
            form.patch(`/admin/users/${editing.id}`, {
                preserveScroll: true,
                onSuccess: () => closeModal(),
            });
            return;
        }

        form.post('/admin/users', {
            preserveScroll: true,
            onSuccess: () => closeModal(),
        });
    };

    const selfEdit = modal === 'edit' && editing?.is_self;
    const hasFilters = values.search !== '' || values.status !== '' || values.role_id !== '';

    return (
        <AppLayout>
            <Head title="User accounts" />

            <div className="space-y-6">
                <PageHeader
                    eyebrow="Administration"
                    title="User accounts"
                    description="Provision accounts and assign a single role with the matching hospital, region, or province scope. There is no public registration."
                    actions={
                        <Button onClick={openCreate}>
                            <UserPlus className="size-4" />
                            Add user
                        </Button>
                    }
                />

                <div className="grid gap-3 sm:grid-cols-3">
                    <StatCard label="Accounts in scope" value={formatNumber(users?.total)} hint="Matching current filters" icon={Users} tone="brand" />
                    <StatCard label="Hospitals available" value={formatNumber(hospitals.length)} hint="Assignments permitted in your scope" icon={Building2} tone="teal" />
                    <StatCard
                        label="Roles available"
                        value={formatNumber(roles.length)}
                        hint={isSuperAdmin ? 'Includes Super Administrator' : isNetworkUser ? 'Network, region, province, and hospital' : 'Hospital-scoped roles only'}
                        icon={ShieldCheck}
                        tone="violet"
                    />
                </div>

                <Card className="p-4 sm:p-5">
                    <form onSubmit={applyFilters} className="flex flex-col gap-3 lg:flex-row lg:items-end">
                        <Field label="Search" htmlFor="user-search" className="lg:w-72">
                            <div className="relative">
                                <Search className="pointer-events-none absolute top-1/2 left-3.5 size-4 -translate-y-1/2 text-ink-400" />
                                <input
                                    id="user-search"
                                    type="search"
                                    value={values.search}
                                    onChange={(event) => setValues((current) => ({ ...current, search: event.target.value }))}
                                    placeholder="Name or email"
                                    className="input pl-10"
                                />
                            </div>
                        </Field>

                        <Field label="Status" htmlFor="user-status" className="lg:w-44">
                            <Select
                                id="user-status"
                                value={values.status}
                                onChange={(event) => setValues((current) => ({ ...current, status: event.target.value }))}
                            >
                                <option value="">All statuses</option>
                                {STATUS_OPTIONS.map((option) => (
                                    <option key={option.value} value={option.value}>
                                        {option.label}
                                    </option>
                                ))}
                            </Select>
                        </Field>

                        <Field label="Role" htmlFor="user-role-filter" className="lg:w-64">
                            <Select
                                id="user-role-filter"
                                value={values.role_id}
                                onChange={(event) => setValues((current) => ({ ...current, role_id: event.target.value }))}
                            >
                                <option value="">All roles</option>
                                {roles.map((role) => (
                                    <option key={role.id} value={role.id}>
                                        {role.name}
                                    </option>
                                ))}
                            </Select>
                        </Field>

                        <div className="flex items-center gap-2 lg:ml-auto">
                            <Button type="button" variant="ghost" size="sm" onClick={resetFilters} disabled={!hasFilters}>
                                <FilterX className="size-4" />
                                Reset
                            </Button>
                            <Button type="submit" size="sm">
                                <SlidersHorizontal className="size-4" />
                                Apply
                            </Button>
                        </div>
                    </form>
                </Card>

                <Card className="overflow-hidden">
                    {users?.data?.length ? (
                        <>
                            <div className="overflow-x-auto">
                                <table className="table">
                                    <thead>
                                        <tr>
                                            <th>User</th>
                                            <th>Access</th>
                                            <th>Status</th>
                                            <th>Last sign-in</th>
                                            <th>Created</th>
                                            <th aria-label="Actions" />
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {users.data.map((user) => (
                                            <tr key={user.id}>
                                                <td>
                                                    <div className="flex items-start gap-2.5">
                                                        <Avatar name={user.name} size="md" />
                                                        <div className="min-w-0">
                                                            <p className="flex flex-wrap items-center gap-1.5 text-xs font-bold text-ink-900">
                                                                <span className="max-w-48 truncate">{user.name}</span>
                                                                {user.is_self && <Badge tone="brand">You</Badge>}
                                                            </p>
                                                            <p className="mt-0.5 max-w-56 truncate text-[11px] text-ink-500">{user.email}</p>
                                                            {user.phone && <p className="mt-0.5 text-[10px] text-ink-400">{user.phone}</p>}
                                                        </div>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div className="flex max-w-72 flex-col items-start gap-1">
                                                        {user.global_roles?.map((role) => (
                                                            <Badge key={`global-${role.id}`} tone={SCOPE_TONE[role.scope_type] ?? 'slate'}>
                                                                <ShieldCheck className="size-3" />
                                                                {role.name}
                                                            </Badge>
                                                        ))}
                                                        {user.memberships?.map((membership) => (
                                                            <Badge key={`membership-${membership.id}`} tone="teal">
                                                                <Building2 className="size-3" />
                                                                {membership.role} · {membership.hospital}
                                                            </Badge>
                                                        ))}
                                                        {user.admin_scopes?.map((scope, index) => (
                                                            <Badge key={`scope-${index}`} tone="sky">
                                                                <MapPin className="size-3" />
                                                                {[scope.region, scope.province].filter(Boolean).join(' · ') || 'No scope'}
                                                            </Badge>
                                                        ))}
                                                        {!user.global_roles?.length && !user.memberships?.length && !user.admin_scopes?.length && (
                                                            <Badge tone="slate">Unassigned</Badge>
                                                        )}
                                                    </div>
                                                </td>
                                                <td>
                                                    <Badge tone={toneForUserStatus(user.status)} dot>
                                                        {humanize(user.status)}
                                                    </Badge>
                                                </td>
                                                <td>
                                                    <span className="block whitespace-nowrap text-xs text-ink-600">
                                                        {user.last_login_at ? relativeTime(user.last_login_at) : 'Never'}
                                                    </span>
                                                    <span className="mt-0.5 block whitespace-nowrap text-[10px] text-ink-400">
                                                        {formatDateTime(user.last_login_at)}
                                                    </span>
                                                </td>
                                                <td>
                                                    <span className="whitespace-nowrap text-xs text-ink-600">{formatDateTime(user.created_at)}</span>
                                                </td>
                                                <td>
                                                    <div className="flex justify-end">
                                                        <Button variant="secondary" size="sm" onClick={() => openEdit(user)}>
                                                            <Pencil className="size-3.5" />
                                                            Edit
                                                        </Button>
                                                    </div>
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                            <div className="border-t border-ink-100 px-5 py-4">
                                <Pagination paginator={users} />
                            </div>
                        </>
                    ) : (
                        <EmptyState
                            icon={UserRound}
                            title="No users match these filters"
                            description="Try clearing the search, status, or role filter. New accounts must be provisioned by an administrator."
                            action={
                                <div className="flex items-center gap-2">
                                    <Button variant="secondary" onClick={resetFilters}>
                                        Clear filters
                                    </Button>
                                    <Button onClick={openCreate}>
                                        <UserPlus className="size-4" />
                                        Add user
                                    </Button>
                                </div>
                            }
                        />
                    )}
                </Card>

                <p className="text-[11px] leading-5 text-ink-400">
                    Each account receives one role. Hospital roles require a hospital membership; regional and provincial administrators receive an administrative scope.
                    Role and scope changes are written to the audit trail.
                </p>
            </div>

            <Modal
                open={modal !== null}
                onClose={closeModal}
                title={modal === 'edit' ? `Edit ${editing?.name}` : 'Add a user'}
                description={
                    selfEdit
                        ? 'Update your own account details. Role and scope changes must be made by another administrator.'
                        : 'Create or update an account and its single role assignment.'
                }
                icon={modal === 'edit' ? Pencil : UserPlus}
                size="lg"
                footer={
                    <>
                        <Button variant="secondary" onClick={closeModal} disabled={form.processing}>
                            Cancel
                        </Button>
                        <Button onClick={submit} disabled={form.processing}>
                            {form.processing ? (
                                <>
                                    <span className="size-4 animate-spin rounded-full border-2 border-white/30 border-t-white" />
                                    Saving…
                                </>
                            ) : (
                                <>
                                    <KeyRound className="size-4" />
                                    {modal === 'edit' ? 'Save changes' : 'Create account'}
                                </>
                            )}
                        </Button>
                    </>
                }
            >
                <form onSubmit={submit} className="space-y-5">
                    <div className="grid gap-4 sm:grid-cols-2">
                        <Field label="Full name" htmlFor="user-name" error={form.errors.name} required>
                            <TextInput
                                id="user-name"
                                value={form.data.name}
                                onChange={(event) => form.setData('name', event.target.value)}
                                error={form.errors.name}
                                placeholder="e.g. Maria Santos"
                            />
                        </Field>

                        <Field label="Email" htmlFor="user-email" error={form.errors.email} required>
                            <TextInput
                                id="user-email"
                                type="email"
                                value={form.data.email}
                                onChange={(event) => form.setData('email', event.target.value)}
                                error={form.errors.email}
                                placeholder="name@hospital.ph"
                            />
                        </Field>

                        <Field label="Phone" htmlFor="user-phone" error={form.errors.phone}>
                            <TextInput
                                id="user-phone"
                                value={form.data.phone}
                                onChange={(event) => form.setData('phone', event.target.value)}
                                error={form.errors.phone}
                                placeholder="+63 917 000 0000"
                            />
                        </Field>

                        <Field
                            label="Account status"
                            htmlFor="edit-user-status"
                            error={form.errors.status}
                            hint={selfEdit ? 'You cannot suspend or deactivate your own account.' : undefined}
                            required
                        >
                            <Select
                                id="edit-user-status"
                                value={form.data.status}
                                onChange={(event) => form.setData('status', event.target.value)}
                                error={form.errors.status}
                                disabled={selfEdit}
                            >
                                {STATUS_OPTIONS.map((option) => (
                                    <option key={option.value} value={option.value}>
                                        {option.label}
                                    </option>
                                ))}
                            </Select>
                        </Field>

                        <Field
                            label={modal === 'edit' ? 'New password' : 'Password'}
                            htmlFor="user-password"
                            error={form.errors.password}
                            hint={modal === 'edit' ? 'Leave blank to keep the current password.' : 'At least 10 characters with upper and lower case letters and a number.'}
                            required={modal !== 'edit'}
                        >
                            <TextInput
                                id="user-password"
                                type="password"
                                value={form.data.password}
                                onChange={(event) => form.setData('password', event.target.value)}
                                error={form.errors.password}
                                autoComplete="new-password"
                                placeholder={modal === 'edit' ? 'Unchanged' : 'Minimum 10 characters'}
                            />
                        </Field>

                        <Field label="Confirm password" htmlFor="user-password-confirm" error={form.errors.password_confirmation} required={modal !== 'edit'}>
                            <TextInput
                                id="user-password-confirm"
                                type="password"
                                value={form.data.password_confirmation}
                                onChange={(event) => form.setData('password_confirmation', event.target.value)}
                                error={form.errors.password_confirmation}
                                autoComplete="new-password"
                                placeholder="Repeat the password"
                            />
                        </Field>
                    </div>

                    {selfEdit ? (
                        <InfoCallout tone="slate" title="Self-service edit">
                            Your role and administrative scope are unchanged. Another authorized administrator can reassign them if needed.
                        </InfoCallout>
                    ) : (
                        <div className="space-y-4 border-t border-ink-100 pt-5">
                            <div>
                                <h3 className="heading-3">Role and scope</h3>
                                <p className="subtle mt-0.5">
                                    {isNetworkUser
                                        ? 'Network roles apply across the platform; hospital roles require a hospital membership.'
                                        : 'Your account can assign hospital-scoped roles only.'}
                                </p>
                            </div>

                            <Field label="Role" htmlFor="edit-user-role" error={form.errors.role_id} required>
                                <Select id="edit-user-role" value={form.data.role_id} onChange={(event) => changeRole(event.target.value)} error={form.errors.role_id}>
                                    <option value="">Select a role</option>
                                    {roles.map((role) => (
                                        <option key={role.id} value={role.id}>
                                            {role.name} · {humanize(role.scope_type)}
                                        </option>
                                    ))}
                                </Select>
                            </Field>

                            {selectedRole?.scope_type === 'hospital' && (
                                <Field label="Hospital assignment" htmlFor="edit-user-hospital" error={form.errors.hospital_id} required>
                                    <Select
                                        id="edit-user-hospital"
                                        value={form.data.hospital_id}
                                        onChange={(event) => form.setData('hospital_id', event.target.value)}
                                        error={form.errors.hospital_id}
                                    >
                                        <option value="">Select a hospital</option>
                                        {hospitals.map((hospital) => (
                                            <option key={hospital.id} value={hospital.id}>
                                                {hospital.name}
                                            </option>
                                        ))}
                                    </Select>
                                </Field>
                            )}

                            {selectedRole?.scope_type === 'region' && (
                                <Field label="Regional scope" htmlFor="edit-user-region" error={form.errors.region_id} required>
                                    <Select
                                        id="edit-user-region"
                                        value={form.data.region_id}
                                        onChange={(event) => form.setData('region_id', event.target.value)}
                                        error={form.errors.region_id}
                                    >
                                        <option value="">Select a region</option>
                                        {regions.map((region) => (
                                            <option key={region.id} value={region.id}>
                                                {region.name}
                                            </option>
                                        ))}
                                    </Select>
                                </Field>
                            )}

                            {selectedRole?.scope_type === 'province' && (
                                <Field label="Provincial scope" htmlFor="edit-user-province" error={form.errors.province_id} required>
                                    <Select
                                        id="edit-user-province"
                                        value={form.data.province_id}
                                        onChange={(event) => form.setData('province_id', event.target.value)}
                                        error={form.errors.province_id}
                                    >
                                        <option value="">Select a province</option>
                                        {provinceGroups.map(([region, items]) => (
                                            <optgroup key={region} label={region}>
                                                {items.map((province) => (
                                                    <option key={province.id} value={province.id}>
                                                        {province.name}
                                                    </option>
                                                ))}
                                            </optgroup>
                                        ))}
                                    </Select>
                                </Field>
                            )}

                            {selectedRole && ['network'].includes(selectedRole.scope_type) && (
                                <InfoCallout tone="brand">
                                    This is a network-scoped role. It does not require a hospital, region, or province assignment.
                                </InfoCallout>
                            )}

                            {form.errors.role_id && <p className="field-error">{form.errors.role_id}</p>}
                        </div>
                    )}
                </form>
            </Modal>
        </AppLayout>
    );
}
