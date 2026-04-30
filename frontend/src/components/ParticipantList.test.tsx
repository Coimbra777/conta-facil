import { describe, it, expect } from "vitest";
import { render, screen } from "@testing-library/react";
import { ParticipantList } from "./ParticipantList";

describe("ParticipantList", () => {
    it("renders participant phones with Brazilian display formatting", () => {
        render(
            <ParticipantList
                participants={[
                    {
                        id: "p1",
                        name: "Alice",
                        phone: "11999998888",
                        amount: 25,
                        status: "pending",
                    },
                    {
                        id: "p2",
                        name: "Bruno",
                        phone: "1133334444",
                        amount: 30,
                        status: "validated",
                    },
                ]}
            />,
        );

        expect(screen.getByText("(11) 99999-8888")).toBeInTheDocument();
        expect(screen.getByText("(11) 3333-4444")).toBeInTheDocument();
    });
});
