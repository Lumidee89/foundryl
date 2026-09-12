import { useState, useId, type InputHTMLAttributes } from "react";
import { Eye, EyeOff } from "lucide-react";
export default function PasswordInput({
    label = "Password",
    ...props
}: InputHTMLAttributes<HTMLInputElement> & { label?: string }) {
    const [visible, setVisible] = useState(false);
    const id = useId();
    return (
        <span className="password-field">
            <input
                {...props}
                id={props.id || id}
                type={visible ? "text" : "password"}
            />
            <button
                type="button"
                className="password-toggle"
                aria-label={`${visible ? "Hide" : "Show"} ${label.toLowerCase()}`}
                aria-controls={props.id || id}
                aria-pressed={visible}
                onClick={() => setVisible(!visible)}
            >
                {visible ? <EyeOff size={19} /> : <Eye size={19} />}
            </button>
        </span>
    );
}
