import { router } from '@inertiajs/react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { destroy as destroyMember } from '@/routes/team/members';
import type { TeamMember } from '@/types';

type Props = {
    member: TeamMember | null;
    open: boolean;
    onOpenChange: (open: boolean) => void;
};

export default function RemoveMemberModal({
    member,
    open,
    onOpenChange,
}: Props) {
    const [processing, setProcessing] = useState(false);

    const removeMember = () => {
        if (!member) {
            return;
        }

        router.visit(destroyMember(member.id), {
            onStart: () => setProcessing(true),
            onFinish: () => setProcessing(false),
            onSuccess: () => onOpenChange(false),
        });
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Remover membro da equipe</DialogTitle>
                    <DialogDescription>
                        Tem certeza de que deseja remover{' '}
                        <strong>{member?.name}</strong> desta equipe?
                    </DialogDescription>
                </DialogHeader>

                <DialogFooter className="gap-2">
                    <DialogClose asChild>
                        <Button variant="secondary">Cancelar</Button>
                    </DialogClose>

                    <Button
                        variant="destructive"
                        data-test="remove-member-confirm"
                        disabled={processing}
                        onClick={removeMember}
                    >
                        Remover membro
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
