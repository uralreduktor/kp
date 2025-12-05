import { Check } from 'lucide-react';
import { cn } from '@/lib/utils';

interface StepperProps {
  steps: string[];
  currentStep: number;
  onStepClick: (step: number) => void;
  readOnly?: boolean;
}

export function Stepper({ steps, currentStep, onStepClick, readOnly = false }: StepperProps) {
  return (
    <nav aria-label="Progress" className="mb-8">
      <ol role="list" className="flex items-center">
        {steps.map((step, stepIdx) => {
          const isComplete = currentStep > stepIdx;
          const isCurrent = currentStep === stepIdx;
          // В режиме просмотра разрешаем кликать на любые шаги
          const isDisabled = !readOnly && !isComplete && !isCurrent;

          return (
            <li key={step} className={cn(stepIdx !== steps.length - 1 ? 'pr-8 sm:pr-20' : '', 'relative')}>
              {stepIdx !== steps.length - 1 ? (
                <div className="absolute top-4 left-0 -ml-px mt-0.5 h-0.5 w-full bg-gray-200" aria-hidden="true">
                    <div className={cn("h-full bg-blue-600 transition-all duration-500", isComplete ? 'w-full' : 'w-0')} />
                </div>
              ) : null}
              
              <button 
                type="button"
                onClick={(e) => {
                  e.preventDefault();
                  onStepClick(stepIdx);
                }}
                className="group relative flex flex-col items-center focus:outline-none"
                disabled={isDisabled}
              >
                <span className="flex h-8 w-8 items-center justify-center rounded-full bg-white hover:bg-gray-50">
                  {isComplete ? (
                    <span className="h-8 w-8 rounded-full bg-blue-600 flex items-center justify-center">
                      <Check className="h-5 w-5 text-white" aria-hidden="true" />
                    </span>
                  ) : isCurrent ? (
                    <span className="h-8 w-8 rounded-full border-2 border-blue-600 flex items-center justify-center" aria-current="step">
                      <span className="h-2.5 w-2.5 rounded-full bg-blue-600" />
                    </span>
                  ) : (
                    <span className="h-8 w-8 rounded-full border-2 border-gray-300 flex items-center justify-center group-hover:border-gray-400">
                      <span className="h-2.5 w-2.5 rounded-full bg-transparent group-hover:bg-gray-300" />
                    </span>
                  )}
                </span>
                <span className={cn(
                    "mt-2 text-sm font-medium",
                    isCurrent ? "text-blue-600" : "text-gray-500"
                )}>
                  {step}
                </span>
              </button>
            </li>
          );
        })}
      </ol>
    </nav>
  );
}


