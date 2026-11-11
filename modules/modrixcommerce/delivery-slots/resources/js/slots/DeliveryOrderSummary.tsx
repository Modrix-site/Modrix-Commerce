import { Calendar, Clock, FileText, Truck } from 'lucide-react';
import { Card, CardContent } from '@/components/ui/card';

type Props = {
    delivery_date: string;
    slot_name: string;
    slot_window: string | null;
    instructions: string | null;
};

export default function DeliveryOrderSummary({
    delivery_date,
    slot_name,
    slot_window,
    instructions,
}: Props) {
    if (!delivery_date) {
        return null;
    }

    return (
        <Card className="my-4 border-sky-500/20 bg-sky-50/40 shadow-xs dark:bg-sky-950/20">
            <CardContent className="p-4 sm:p-5">
                <div className="flex items-center gap-2 border-b border-border/50 pb-2.5">
                    <Truck className="size-4 text-sky-600 dark:text-sky-400" />
                    <h4 className="text-sm font-bold text-foreground">
                        Scheduled Delivery Information
                    </h4>
                </div>

                <div className="mt-3 grid grid-cols-1 gap-3 sm:grid-cols-2">
                    <div className="flex items-start gap-2">
                        <Calendar className="mt-0.5 size-4 text-muted-foreground" />
                        <div>
                            <span className="text-[11px] font-medium tracking-wider text-muted-foreground uppercase">
                                Scheduled Date
                            </span>
                            <p className="text-xs font-semibold text-foreground">
                                {delivery_date}
                            </p>
                        </div>
                    </div>

                    <div className="flex items-start gap-2">
                        <Clock className="mt-0.5 size-4 text-muted-foreground" />
                        <div>
                            <span className="text-[11px] font-medium tracking-wider text-muted-foreground uppercase">
                                Time Window
                            </span>
                            <p className="text-xs font-semibold text-foreground">
                                {slot_name}{' '}
                                {slot_window ? `(${slot_window})` : ''}
                            </p>
                        </div>
                    </div>
                </div>

                {instructions && (
                    <div className="mt-3 flex items-start gap-2 rounded-md border border-border/40 bg-background/60 p-2.5">
                        <FileText className="mt-0.5 size-3.5 text-muted-foreground" />
                        <div className="text-xs">
                            <span className="font-medium text-foreground">
                                Delivery Instructions:{' '}
                            </span>
                            <span className="text-muted-foreground">
                                {instructions}
                            </span>
                        </div>
                    </div>
                )}
            </CardContent>
        </Card>
    );
}
