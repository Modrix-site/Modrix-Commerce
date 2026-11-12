import { Checkbox } from '@/components/ui/checkbox';
import type { AssignableRole } from '@/types';

/**
 * Role picker. Roles the current user may not grant are shown but disabled;
 * the server applies the same rule, so this is only a usability hint.
 * Disabled checkboxes are not submitted, so locked roles that are currently
 * assigned are sent through hidden inputs to keep them unchanged.
 */
export function RoleCheckboxes({
    roles,
    selected,
    disabled = false,
}: {
    roles: AssignableRole[];
    selected: number[];
    disabled?: boolean;
}) {
    return (
        <fieldset className="grid gap-3" disabled={disabled}>
            <legend className="sr-only">Roles</legend>
            {roles.map((role) => {
                const checked = selected.includes(role.id);
                const locked = disabled || !role.grantable;

                return (
                    <div key={role.id} className="flex items-start gap-3">
                        <Checkbox
                            id={`role-${role.id}`}
                            name="roles[]"
                            value={String(role.id)}
                            defaultChecked={checked}
                            disabled={locked}
                        />
                        {locked && checked && (
                            <input
                                type="hidden"
                                name="roles[]"
                                value={role.id}
                            />
                        )}
                        <label
                            htmlFor={`role-${role.id}`}
                            className="grid gap-0.5 text-sm"
                        >
                            <span className="font-medium">
                                {role.display_name}
                                {!role.grantable && (
                                    <span className="ml-2 text-xs font-normal text-muted-foreground">
                                        (you cannot grant or revoke this role)
                                    </span>
                                )}
                            </span>
                            {role.description && (
                                <span className="text-muted-foreground">
                                    {role.description}
                                </span>
                            )}
                        </label>
                    </div>
                );
            })}
        </fieldset>
    );
}
