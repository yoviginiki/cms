import { useId } from 'react';

interface TextFieldProps {
  label: string;
  value: string;
  onChange: (val: string) => void;
  placeholder?: string;
  helperText?: string;
}

export function TextField({ label, value, onChange, placeholder, helperText }: TextFieldProps) {
  const id = useId();
  return (
    <div>
      <label htmlFor={id} className="block text-[11px] font-medium text-base-content/50 mb-1">{label}</label>
      <input
        id={id}
        name={id}
        type="text"
        value={value}
        onChange={(e) => onChange(e.target.value)}
        placeholder={placeholder}
        className="input input-bordered input-sm w-full text-[12px]"
      />
      {helperText && <p className="text-[10px] text-base-content/40 mt-0.5">{helperText}</p>}
    </div>
  );
}
